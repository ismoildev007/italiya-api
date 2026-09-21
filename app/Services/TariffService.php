<?php

namespace App\Services;

use App\Models\Tariff;
use Illuminate\Database\Eloquent\Collection;

class TariffService
{
    /** @return Collection<int, Tariff> */
    public function list(array $filters = []): Collection
    {
        return Tariff::withCount(['studentTariffs' => fn ($q) => $q->where('is_active', true)])
            ->filter($filters, ['is_active' => ['is_active', 'bool']])
            ->orderBy('monthly_price')
            ->get();
    }

    public function create(array $data): Tariff
    {
        return Tariff::create($data);
    }

    public function update(Tariff $tariff, array $data): Tariff
    {
        $tariff->update($data);

        return $tariff->fresh();
    }

    public function delete(Tariff $tariff): void
    {
        abort_if(
            $tariff->studentTariffs()->where('is_active', true)->exists(),
            422,
            'Bu tarifda faol o\'quvchilar bor — avval ularni boshqa tarifga o\'tkazing.'
        );

        $tariff->delete();
    }
}
