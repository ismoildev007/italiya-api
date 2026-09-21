<?php

namespace App\Models\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ro'yxat so'rovlaridagi bir xil `->when(...)` zanjirlarini bitta joyga yig'adi.
 *
 * Xarita (`$map`) kalitlari — filtr nomlari, qiymatlari:
 *   'class_id'                    → where('class_id', $value)
 *   ['grade', 'equals']           → where('grade', $value)
 *   ['name', 'like']              → where('name', 'like', "%$value%")
 *   ['date', 'date']              → whereDate('date', $value)
 *   ['date', 'date_from']         → whereDate('date', '>=', $value)
 *   ['date', 'date_to']           → whereDate('date', '<=', $value)
 *   ['is_active', 'bool']         → where('is_active', (bool) $value)
 *   ['student.class_id', 'has']   → whereHas('student', fn ($q) => $q->where('class_id', $value))
 *   fn (Builder $q, $value) => …  → ixtiyoriy shart
 */
trait Filterable
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, string|array{0:string,1:string}|Closure>  $map
     */
    public function scopeFilter(Builder $query, array $filters, array $map): Builder
    {
        foreach ($map as $key => $rule) {
            if (! array_key_exists($key, $filters)) {
                continue;
            }

            $value = $filters[$key];

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $this->applyFilterRule($query, $rule, $value);
        }

        return $query;
    }

    /**
     * Bir nechta ustun bo'yicha qidiruv.
     *
     * @param  array<int, string>  $columns
     */
    public function scopeSearch(Builder $query, ?string $term, array $columns): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term, $columns) {
            foreach ($columns as $column) {
                str_contains($column, '.')
                    ? $this->searchRelation($q, $column, $term)
                    : $q->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    private function applyFilterRule(Builder $query, string|array|Closure $rule, mixed $value): void
    {
        if ($rule instanceof Closure) {
            $rule($query, $value);

            return;
        }

        if (is_string($rule)) {
            $query->where($rule, $value);

            return;
        }

        [$column, $type] = $rule + [1 => 'equals'];

        match ($type) {
            'like' => $query->where($column, 'like', "%{$value}%"),
            'date' => $query->whereDate($column, $value),
            'date_from' => $query->whereDate($column, '>=', $value),
            'date_to' => $query->whereDate($column, '<=', $value),
            'bool' => $query->where($column, filter_var($value, FILTER_VALIDATE_BOOLEAN)),
            'in' => $query->whereIn($column, (array) $value),
            'has' => $this->filterRelation($query, $column, $value),
            default => $query->where($column, $value),
        };
    }

    /** `relation.column` ko'rinishidagi filtr. */
    private function filterRelation(Builder $query, string $path, mixed $value): void
    {
        [$relation, $column] = $this->splitPath($path);

        $query->whereHas($relation, fn (Builder $q) => $q->where($column, $value));
    }

    private function searchRelation(Builder $query, string $path, string $term): void
    {
        [$relation, $column] = $this->splitPath($path);

        $query->orWhereHas($relation, fn (Builder $q) => $q->where($column, 'like', "%{$term}%"));
    }

    /** @return array{0: string, 1: string} */
    private function splitPath(string $path): array
    {
        $parts = explode('.', $path);
        $column = array_pop($parts);

        return [implode('.', $parts), $column];
    }
}
