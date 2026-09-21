<?php

namespace App\Http\Requests\Tariff;

use App\Http\Requests\BaseRequest;

class StoreTariffRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150|unique:tariffs,name',
            'monthly_price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
