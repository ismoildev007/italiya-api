<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

class StoreInvoiceRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->monthRules('required') + [
            'student_id' => 'required|exists:students,id',
        ];
    }
}
