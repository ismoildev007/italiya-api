<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TariffResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'monthly_price' => (float) $this->monthly_price,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'students_count' => $this->whenCounted('studentTariffs'),
        ];
    }
}
