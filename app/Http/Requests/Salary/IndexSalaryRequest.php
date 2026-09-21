<?php

namespace App\Http\Requests\Salary;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

class IndexSalaryRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->paginationRules() + $this->monthRules() + [
            'teacher_id' => 'nullable|exists:teachers,id',
            'status' => 'nullable|in:pending,paid',
        ];
    }
}
