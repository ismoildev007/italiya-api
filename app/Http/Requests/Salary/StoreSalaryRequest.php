<?php

namespace App\Http\Requests\Salary;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

class StoreSalaryRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->monthRules('required') + [
            'teacher_id' => 'required|exists:teachers,id',
            'bonus' => 'nullable|numeric|min:0',
            'deduction' => 'nullable|numeric|min:0',
        ];
    }
}
