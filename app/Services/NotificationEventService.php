<?php

namespace App\Services;

use App\Models\NotificationEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Telegram bot hodisalari navbati bilan ishlash (stub).
 */
class NotificationEventService
{
    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return NotificationEvent::query()
            ->filter($filters, [
                'status' => 'status',
                'type' => 'type',
                'student_id' => 'student_id',
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Yuborilishi kutilayotgan hodisalar — bot shu ro'yxatni o'qiydi.
     *
     * @return Collection<int, NotificationEvent>
     */
    public function pending(int $limit = 50): Collection
    {
        return NotificationEvent::pending()
            ->whereNotNull('recipient_address')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function markSent(NotificationEvent $event): NotificationEvent
    {
        $event->update([
            'status' => NotificationEvent::STATUS_SENT,
            'sent_at' => now(),
            'attempts' => $event->attempts + 1,
        ]);

        return $event->fresh();
    }

    public function markFailed(NotificationEvent $event, ?string $error = null): NotificationEvent
    {
        $event->update([
            'status' => NotificationEvent::STATUS_FAILED,
            'error' => $error,
            'attempts' => $event->attempts + 1,
        ]);

        return $event->fresh();
    }
}
