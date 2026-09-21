<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Carbon;

/**
 * Davr bo'yicha filtrlar — hisobotlar va ro'yxatlarda bir xil takrorlanadi.
 */
trait HasPeriodFilters
{
    /**
     * Davr oralig'i — servislarga tayyor Carbon juftligi.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function periodRange(?Carbon $defaultFrom = null, ?Carbon $defaultTo = null): array
    {
        return [
            $this->filled('from') ? Carbon::parse($this->input('from'))->startOfDay() : ($defaultFrom ?? now()->startOfMonth()),
            $this->filled('to') ? Carbon::parse($this->input('to'))->endOfDay() : ($defaultTo ?? now()),
        ];
    }

    protected function periodRules(): array
    {
        return [
            'period' => 'nullable|in:day,week,month,year',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ];
    }

    protected function monthRules(string $presence = 'nullable'): array
    {
        return [
            'year' => "{$presence}|integer|min:2020|max:2100",
            'month' => "{$presence}|integer|min:1|max:12",
        ];
    }
}
