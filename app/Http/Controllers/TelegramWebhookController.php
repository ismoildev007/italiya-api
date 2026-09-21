<?php

namespace App\Http\Controllers;

use App\Services\Telegram\ParentBotService;
use App\Services\Telegram\StudentBotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Telegram webhook'lari (ommaviy). Har bir bot o'z maxfiy yo'li bilan chaqiriladi:
 *   POST /telegram/student/{secret}  — o'quvchilar boti (test yechish)
 *   POST /telegram/parent/{secret}   — ota-onalar boti (qarzdorlik, hisobot)
 */
class TelegramWebhookController extends Controller
{
    public function student(Request $request, string $secret, StudentBotService $bot): JsonResponse
    {
        $this->guard($secret, (string) config('services.telegram.student_webhook_secret'));

        $bot->handle($request->all());

        return $this->ok(null);
    }

    public function parent(Request $request, string $secret, ParentBotService $bot): JsonResponse
    {
        $this->guard($secret, (string) config('services.telegram.webhook_secret'));

        $bot->handle($request->all());

        return $this->ok(null);
    }

    private function guard(string $given, string $expected): void
    {
        abort_if($expected === '' || ! hash_equals($expected, $given), 403, 'Webhook secret noto\'g\'ri.');
    }
}
