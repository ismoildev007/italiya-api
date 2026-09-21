<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Telegram Bot API uchun yupqa mijoz. Ikkita bot bilan ishlaydi:
 *   parent  — ota-onalar (qarzdorlik, oylik hisobot, baho, davomat)
 *   student — o'quvchilar (faqat variantli testlarni yechish)
 */
class TelegramClient
{
    public function __construct(private readonly string $token) {}

    public static function parent(): self
    {
        return new self((string) config('services.telegram.token'));
    }

    public static function student(): self
    {
        return new self((string) config('services.telegram.student_token'));
    }

    public function isConfigured(): bool
    {
        return $this->token !== '';
    }

    public function sendMessage(string|int $chatId, string $text, ?array $replyMarkup = null, string $parseMode = 'HTML'): Response
    {
        return $this->call('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'reply_markup' => $replyMarkup ? json_encode($replyMarkup) : null,
            'disable_web_page_preview' => true,
        ]));
    }

    public function editMessageText(string|int $chatId, int $messageId, string $text, ?array $replyMarkup = null): Response
    {
        return $this->call('editMessageText', array_filter([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $replyMarkup ? json_encode($replyMarkup) : null,
        ]));
    }

    public function answerCallbackQuery(string $callbackId, ?string $text = null): Response
    {
        return $this->call('answerCallbackQuery', array_filter(['callback_query_id' => $callbackId, 'text' => $text]));
    }

    public function sendDocument(string|int $chatId, string $absolutePath, ?string $caption = null): Response
    {
        return Http::timeout(60)
            ->attach('document', file_get_contents($absolutePath), basename($absolutePath))
            ->post($this->url('sendDocument'), array_filter(['chat_id' => $chatId, 'caption' => $caption, 'parse_mode' => 'HTML']));
    }

    public function setWebhook(string $url, ?string $secret = null): Response
    {
        return $this->call('setWebhook', array_filter(['url' => $url, 'secret_token' => $secret]));
    }

    public function call(string $method, array $params = []): Response
    {
        return Http::timeout(30)->post($this->url($method), $params);
    }

    private function url(string $method): string
    {
        return "https://api.telegram.org/bot{$this->token}/{$method}";
    }
}
