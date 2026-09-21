<?php

namespace App\Services;

use App\Models\TestAnswer;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\SpeechService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Ovozli javoblarni matnga o'giradi: asosiy — OpenAI STT, zaxira — Gemini.
 * Original audio fayl HAM saqlanadi — javob tarixi to'liq qoladi.
 */
class VoiceTranscriptionService
{
    public function __construct(
        private readonly SpeechService $speech,
        private readonly GeminiClient $gemini,
    ) {}

    public function store(TestAnswer $answer, UploadedFile $audio): TestAnswer
    {
        $path = $audio->store('test-answers/audio', 'public');

        $answer->update(['audio_path' => $path]);

        return $this->transcribe($answer);
    }

    public function transcribe(TestAnswer $answer, string $language = 'it'): TestAnswer
    {
        if (! $answer->audio_path) {
            return $answer;
        }

        [$text, $provider] = $this->transcribePath($answer->audio_path, $language);

        if ($text !== '') {
            $answer->update([
                'transcript' => $text,
                'transcription_provider' => $provider,
                'transcribed_at' => now(),
            ]);
        }

        return $answer->fresh();
    }

    /**
     * @return array{0: string, 1: ?string} [transkript, provayder]
     */
    public function transcribePath(string $path, string $language = 'it'): array
    {
        if ($this->speech->isConfigured()) {
            $text = $this->speech->transcribe($path, $language);

            if ($text !== '') {
                return [$text, 'openai'];
            }
        }

        if ($this->gemini->isConfigured() && Storage::disk('public')->exists($path)) {
            try {
                $result = $this->gemini->jsonWithAudio(
                    'Ushbu audioni aynan aytilganidek matnga o\'gir. JSON: {"transcript": "..."}',
                    $path,
                );

                $text = trim((string) ($result['transcript'] ?? ''));

                if ($text !== '') {
                    return [$text, 'gemini'];
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return ['', null];
    }
}
