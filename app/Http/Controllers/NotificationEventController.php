<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexNotificationRequest;
use App\Http\Requests\Notification\MarkFailedRequest;
use App\Http\Resources\NotificationEventResource;
use App\Models\NotificationEvent;
use App\Services\NotificationEventService;
use Illuminate\Http\JsonResponse;

/**
 * Telegram bot integratsiyasi uchun hodisa navbati (stub).
 *
 * Bot/queue worker `pending` hodisalarni o'qiydi, yuboradi va
 * `sent` / `failed` orqali holatni yangilaydi.
 */
class NotificationEventController extends Controller
{
    public function __construct(
        private readonly NotificationEventService $events,
    ) {}

    public function index(IndexNotificationRequest $request): JsonResponse
    {
        return $this->ok(NotificationEventResource::collection(
            $this->events->paginate($request->filters(['limit']), $request->perPage(50))
        ));
    }

    /** Yuborilishi kutilayotgan hodisalar — bot shu endpointdan o'qiydi */
    public function pending(IndexNotificationRequest $request): JsonResponse
    {
        return $this->ok(NotificationEventResource::collection(
            $this->events->pending((int) $request->input('limit', 50))
        ));
    }

    public function show(NotificationEvent $event): JsonResponse
    {
        return $this->ok(new NotificationEventResource($event));
    }

    public function markSent(NotificationEvent $event): JsonResponse
    {
        return $this->updated(
            new NotificationEventResource($this->events->markSent($event)),
            'Yuborilgan deb belgilandi.'
        );
    }

    public function markFailed(MarkFailedRequest $request, NotificationEvent $event): JsonResponse
    {
        return $this->updated(
            new NotificationEventResource($this->events->markFailed($event, $request->input('error'))),
            'Xatolik qayd etildi.'
        );
    }
}
