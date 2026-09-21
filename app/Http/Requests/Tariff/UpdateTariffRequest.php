<?php

namespace App\Http\Requests\Tariff;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateTariffRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150', Rule::unique('tariffs', 'name')->ignore($this->route('tariff')?->id)],
            'monthly_price' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
