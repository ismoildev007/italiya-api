<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;

class AssignTariffRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'tariff_id' => 'required|exists:tariffs,id',
            'starts_on' => 'nullable|date',
        ];
    }
}
