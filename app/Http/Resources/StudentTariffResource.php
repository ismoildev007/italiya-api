<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class StudentTariffResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tariff_id' => $this->tariff_id,
            'name' => $this->tariff?->name,
            'monthly_price' => (float) ($this->tariff?->monthly_price ?? 0),
            'starts_on' => $this->date($this->starts_on),
            'ends_on' => $this->date($this->ends_on),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
