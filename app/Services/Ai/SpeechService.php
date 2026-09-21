<?php

namespace App\Services\Ai;

use App\Services\Ai\Concerns\ParsesJson;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * OpenAI ovoz xizmatlari:
 *   transcribe() — ovozli javobni matnga o'girish (speaking, talaffuz o'yini)
 *   synthesize() — matnni ovozga o'girish (listening bo'limi audiosi, kartochka talaffuzi)
 */
class SpeechService
{
    use ParsesJson;

    public function isConfigured(): bool
    {
        return (bool) config('services.openai.key');
    }

    /**
     * Audio (public diskdagi yo'l yoki absolyut fayl) → transkript.
     * `$language` — ISO kodi (it, uz, en). Kalit bo'lmasa bo'sh qator qaytadi.
     */
    public function transcribe(string $audioPath, ?string $language = null): string
    {
        if (! $this->isConfigured()) {
            return '';
        }

        $disk = Storage::disk('public');
        $bytes = $disk->exists($audioPath) ? $disk->get($audioPath) : (is_file($audioPath) ? file_get_contents($audioPath) : null);

        if ($bytes === null) {
            return '';
        }

        $request = Http::withToken(config('services.openai.key'))
            ->timeout(120)
            ->attach('file', $bytes, basename($audioPath))
            ->asMultipart();

        $payload = [
            'model' => config('services.openai.stt_model', 'gpt-4o-mini-transcribe'),
            'response_format' => 'json',
        ];

        if ($language) {
            $payload['language'] = $language;
        }

        $response = $request->post(config('services.openai.base_url').'/audio/transcriptions', $payload);

        if ($response->failed()) {
            return '';
        }

        return trim((string) $response->json('text', ''));
    }

    /**
     * Matn → mp3 fayl (public diskda). Yo'lni qaytaradi.
     * Listening savollari uchun italyancha matn OpenAI TTS orqali o'qiladi.
     */
    public function synthesize(string $text, string $directory = 'audio/tts', ?string $voice = null, string $instructions = ''): ?string
    {
        if (! $this->isConfigured() || trim($text) === '') {
            return null;
        }

        $body = array_filter([
            'model' => config('services.openai.tts_model', 'gpt-4o-mini-tts'),
            'voice' => $voice ?: config('services.openai.tts_voice', 'alloy'),
            'input' => Str::limit($text, 4000, ''),
            'response_format' => 'mp3',
            'instructions' => $instructions ?: null,
        ]);

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(120)
            ->post(config('services.openai.base_url').'/audio/speech', $body);

        if ($response->failed()) {
            return null;
        }

        $path = trim($directory, '/').'/'.Str::uuid().'.mp3';

        Storage::disk('public')->put($path, $response->body());

        return $path;
    }
}
