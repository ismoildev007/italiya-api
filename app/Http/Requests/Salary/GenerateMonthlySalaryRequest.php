<?php

namespace App\Http\Requests\Salary;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

class GenerateMonthlySalaryRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->monthRules('required');
    }
}
