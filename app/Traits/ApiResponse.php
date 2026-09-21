<?php

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Yagona API javob formati: { success, message, data, meta }.
 *
 * Kontrollerlarda javob yig'ish kodi takrorlanmasligi uchun shu trait ishlatiladi.
 */
trait ApiResponse
{
    protected function ok(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = ['success' => true];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        return response()->json($payload + $this->body($data), $status);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->ok($data, $message ?? 'Muvaffaqiyatli yaratildi.', 201);
    }

    protected function updated(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->ok($data, $message ?? 'Muvaffaqiyatli yangilandi.');
    }

    protected function deleted(?string $message = null): JsonResponse
    {
        return $this->ok(null, $message ?? 'Muvaffaqiyatli o\'chirildi.');
    }

    protected function fail(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message];

        if ($errors) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Sahifalangan resurs to'plamiga `meta` bloki qo'shadi.
     */
    private function body(mixed $data): array
    {
        $paginator = $this->paginatorOf($data);

        if (! $paginator) {
            return ['data' => $data];
        }

        return [
            'data' => $data instanceof ResourceCollection ? $data->collection : $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    private function paginatorOf(mixed $data): ?LengthAwarePaginator
    {
        if ($data instanceof LengthAwarePaginator) {
            return $data;
        }

        if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            return $data->resource;
        }

        return null;
    }
}
