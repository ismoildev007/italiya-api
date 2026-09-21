<?php

namespace App\Services\Ai;

use App\Services\Ai\Concerns\ParsesJson;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class GeminiClient implements AiClient
{
    use ParsesJson;

    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return (string) config('services.gemini.model', 'gemini-2.5-flash');
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.gemini.key');
    }

    public function complete(string $prompt, ?string $system = null, array $options = []): string
    {
        return $this->send([['text' => $prompt]], $system, $options);
    }

    public function json(string $prompt, ?string $system = null, array $options = []): array
    {
        $options['json'] = true;

        return $this->parseJson($this->send([['text' => $prompt]], $system, $options));
    }

    public function jsonWithAudio(string $prompt, string $audioPath, ?string $system = null): array
    {
        $disk = Storage::disk('public');
        $bytes = $disk->exists($audioPath) ? $disk->get($audioPath) : (is_file($audioPath) ? file_get_contents($audioPath) : null);

        $parts = [['text' => $prompt]];

        if ($bytes !== null) {
            $parts[] = ['inline_data' => ['mime_type' => $this->mimeFor($audioPath), 'data' => base64_encode($bytes)]];
        }

        return $this->parseJson($this->send($parts, $system, ['json' => true]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     */
    private function send(array $parts, ?string $system, array $options): string
    {
        abort_unless($this->isConfigured(), 503, 'GEMINI_API_KEY sozlanmagan.');

        $body = [
            'contents' => [['role' => 'user', 'parts' => $parts]],
            'generationConfig' => array_filter([
                'temperature' => $options['temperature'] ?? 0.4,
                'maxOutputTokens' => $options['max_tokens'] ?? 8192,
                'responseMimeType' => ! empty($options['json']) ? 'application/json' : null,
            ]),
        ];

        if ($system) {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }

        $response = Http::timeout($options['timeout'] ?? 120)
            ->post(sprintf(self::ENDPOINT, $this->model()).'?key='.config('services.gemini.key'), $body);

        if ($response->failed()) {
            abort(502, 'Gemini xizmatiga murojaat muvaffaqiyatsiz: '.$response->status());
        }

        return (string) $response->json('candidates.0.content.parts.0.text', '');
    }
}
