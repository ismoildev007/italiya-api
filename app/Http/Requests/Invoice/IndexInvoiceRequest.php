<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

class IndexInvoiceRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->paginationRules() + $this->monthRules() + [
            'student_id' => 'nullable|exists:students,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'status' => 'nullable|in:unpaid,partial,paid,canceled',
        ];
    }
}
