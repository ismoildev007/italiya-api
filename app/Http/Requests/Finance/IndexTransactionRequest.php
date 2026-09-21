<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;

class IndexTransactionRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'type' => 'nullable|in:invoice,payment,discount_change,tariff_change,adjustment,refund',
        ];
    }
}
