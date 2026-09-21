<?php

namespace App\Services\Ai;

use InvalidArgumentException;

/**
 * Vazifa (purpose) bo'yicha AI provayderni tanlaydi:
 *   generator → config('services.ai.generator')  (default: gemini)
 *   evaluator → config('services.ai.evaluator')  (default: gemini)
 */
class AiManager
{
    /** @var array<string, AiClient> */
    private array $resolved = [];

    public function for(string $purpose): AiClient
    {
        return $this->driver((string) config("services.ai.{$purpose}", 'gemini'));
    }

    public function driver(string $name): AiClient
    {
        return $this->resolved[$name] ??= match ($name) {
            'gemini' => app(GeminiClient::class),
            'openai' => app(OpenAiClient::class),
            'anthropic', 'claude' => app(AnthropicClient::class),
            default => throw new InvalidArgumentException("Noma'lum AI provayder: {$name}"),
        };
    }

    /** Barcha provayderlarning holati (admin panel "Sozlamalar" uchun). */
    public function status(): array
    {
        $speech = app(SpeechService::class);

        return [
            'generator' => $this->describe($this->for('generator')),
            'evaluator' => $this->describe($this->for('evaluator')),
            // Tashqi kalit bo'lmasa yoki xato bersa — lokal "oddiy AI" ishlaydi (hech qachon xato qaytarmaydi)
            'fallback' => ['provider' => LocalAi::NAME, 'model' => LocalAi::MODEL, 'configured' => true],
            'stt' => ['provider' => 'openai', 'model' => config('services.openai.stt_model'), 'configured' => $speech->isConfigured()],
            'tts' => ['provider' => 'openai', 'model' => config('services.openai.tts_model'), 'configured' => $speech->isConfigured()],
        ];
    }

    /** Tashqi provayder tayyormi (kalit bor) — bo'lmasa lokal zaxira ishlatiladi. */
    public function remoteAvailable(string $purpose): bool
    {
        try {
            return $this->for($purpose)->isConfigured();
        } catch (\Throwable) {
            return false;
        }
    }

    private function describe(AiClient $client): array
    {
        return ['provider' => $client->name(), 'model' => $client->model(), 'configured' => $client->isConfigured()];
    }
}
