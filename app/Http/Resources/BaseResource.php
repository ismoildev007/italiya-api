<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Resurslarda takrorlanadigan formatlash yordamchilari.
 */
abstract class BaseResource extends JsonResource
{
    protected function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }

    protected function datetime(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateTimeString() : null;
    }

    protected function money(mixed $value): float
    {
        return round((float) $value, 2);
    }

    /** Fayl yo'lidan ochiq havola. */
    protected function fileUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }

    /** Har bir resursda takrorlanadigan vaqt belgilari. */
    protected function timestamps(): array
    {
        return [
            'created_at' => $this->datetime($this->resource->created_at ?? null),
            'updated_at' => $this->datetime($this->resource->updated_at ?? null),
        ];
    }
}
