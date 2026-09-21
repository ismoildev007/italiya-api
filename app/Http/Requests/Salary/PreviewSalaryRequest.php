<?php

namespace App\Http\Requests\Salary;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

class PreviewSalaryRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->monthRules('required') + [
            'bonus' => 'nullable|numeric|min:0',
            'deduction' => 'nullable|numeric|min:0',
        ];
    }
}
