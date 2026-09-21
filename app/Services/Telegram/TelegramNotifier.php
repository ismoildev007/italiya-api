<?php

namespace App\Services\Telegram;

use App\Models\NotificationEvent;
use App\Services\NotificationEventService;

/**
 * `notification_events` navbatidagi xabarlarni Telegram orqali yuboradi:
 *   guardian → ota-onalar boti, student → o'quvchilar boti.
 * Bot tokeni yo'q bo'lsa hodisalar `pending` holatida qoladi.
 */
class TelegramNotifier
{
    public function __construct(private readonly NotificationEventService $events) {}

    /** Yuborilganlar sonini qaytaradi. */
    public function dispatchPending(int $limit = 100): int
    {
        $sent = 0;

        foreach ($this->events->pending($limit) as $event) {
            if ($this->send($event)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function send(NotificationEvent $event): bool
    {
        $client = $event->recipient_type === 'student' ? TelegramClient::student() : TelegramClient::parent();

        if (! $client->isConfigured() || ! config('services.telegram.enabled')) {
            return false;
        }

        if (! $event->recipient_address) {
            $this->events->markFailed($event, 'Qabul qiluvchi botga bog\'lanmagan.');

            return false;
        }

        try {
            $response = $client->sendMessage($event->recipient_address, $this->format($event));
        } catch (\Throwable $e) {
            $this->events->markFailed($event, $e->getMessage());

            return false;
        }

        if ($response->successful() && $response->json('ok')) {
            $this->events->markSent($event);

            return true;
        }

        $this->events->markFailed($event, (string) $response->json('description', 'Telegram xatosi '.$response->status()));

        return false;
    }

    private function format(NotificationEvent $event): string
    {
        $icon = match ($event->type) {
            NotificationEvent::TYPE_DEBT => '💳',
            NotificationEvent::TYPE_PAYMENT => '✅',
            NotificationEvent::TYPE_GRADE => '📝',
            NotificationEvent::TYPE_ATTENDANCE => '🕒',
            NotificationEvent::TYPE_MONTHLY_REPORT => '📊',
            NotificationEvent::TYPE_CERTIFICATE => '🎓',
            NotificationEvent::TYPE_HOMEWORK_ASSIGNED, NotificationEvent::TYPE_HOMEWORK_GRADED => '📚',
            NotificationEvent::TYPE_TEST_ASSIGNED => '🧪',
            NotificationEvent::TYPE_MEETING => '🎥',
            default => 'ℹ️',
        };

        $title = $event->title ? "<b>{$icon} ".e($event->title)."</b>\n" : '';
        $message = $event->type === NotificationEvent::TYPE_MONTHLY_REPORT ? (string) $event->message : e((string) $event->message);

        return $title.$message;
    }
}
