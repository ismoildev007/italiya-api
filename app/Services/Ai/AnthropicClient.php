<?php

namespace App\Services\Ai;

use App\Services\Ai\Concerns\ParsesJson;
use Illuminate\Support\Facades\Http;

class AnthropicClient implements AiClient
{
    use ParsesJson;

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function name(): string
    {
        return 'anthropic';
    }

    public function model(): string
    {
        return (string) config('services.anthropic.model', 'claude-opus-5');
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.anthropic.key');
    }

    public function complete(string $prompt, ?string $system = null, array $options = []): string
    {
        return $this->send($prompt, $system, $options);
    }

    public function json(string $prompt, ?string $system = null, array $options = []): array
    {
        return $this->parseJson($this->send($prompt.' Javobni FAQAT JSON formatida qaytar.', $system, $options));
    }

    public function jsonWithAudio(string $prompt, string $audioPath, ?string $system = null): array
    {
        $transcript = app(SpeechService::class)->transcribe($audioPath);

        return $this->json($prompt."\n\nTranskript:\n".$transcript, $system);
    }

    private function send(string $prompt, ?string $system, array $options): string
    {
        abort_unless($this->isConfigured(), 503, 'ANTHROPIC_API_KEY sozlanmagan.');

        $body = [
            'model' => $this->model(),
            'max_tokens' => $options['max_tokens'] ?? 8000,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];

        if ($system) {
            $body['system'] = $system;
        }

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout($options['timeout'] ?? 120)->post(self::ENDPOINT, $body);

        if ($response->failed()) {
            abort(502, 'Claude xizmatiga murojaat muvaffaqiyatsiz: '.$response->status());
        }

        return (string) $response->json('content.0.text', '');
    }
}
