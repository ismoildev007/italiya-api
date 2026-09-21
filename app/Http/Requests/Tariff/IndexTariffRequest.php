<?php

namespace App\Http\Requests\Tariff;

use App\Http\Requests\BaseRequest;

class IndexTariffRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['is_active' => 'nullable|boolean'];
    }
}
