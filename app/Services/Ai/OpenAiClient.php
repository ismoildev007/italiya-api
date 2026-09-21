<?php

namespace App\Services\Ai;

use App\Services\Ai\Concerns\ParsesJson;
use Illuminate\Support\Facades\Http;

class OpenAiClient implements AiClient
{
    use ParsesJson;

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('services.openai.model', 'gpt-4o-mini');
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.openai.key');
    }

    public function complete(string $prompt, ?string $system = null, array $options = []): string
    {
        return $this->send($prompt, $system, $options);
    }

    public function json(string $prompt, ?string $system = null, array $options = []): array
    {
        $options['json'] = true;

        return $this->parseJson($this->send($prompt, $system, $options));
    }

    public function jsonWithAudio(string $prompt, string $audioPath, ?string $system = null): array
    {
        // Chat completions audio kirishini qo'llamaydi — avval STT, keyin matn tahlili
        $transcript = app(SpeechService::class)->transcribe($audioPath);

        return $this->json($prompt."\n\nTranskript:\n".$transcript, $system);
    }

    private function send(string $prompt, ?string $system, array $options): string
    {
        abort_unless($this->isConfigured(), 503, 'OPENAI_API_KEY sozlanmagan.');

        $messages = [];

        if ($system) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        $body = [
            'model' => $this->model(),
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.4,
            'max_tokens' => $options['max_tokens'] ?? 8000,
        ];

        if (! empty($options['json'])) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::withToken(config('services.openai.key'))
            ->timeout($options['timeout'] ?? 120)
            ->post(config('services.openai.base_url').'/chat/completions', $body);

        if ($response->failed()) {
            abort(502, 'OpenAI xizmatiga murojaat muvaffaqiyatsiz: '.$response->status());
        }

        return (string) $response->json('choices.0.message.content', '');
    }
}
