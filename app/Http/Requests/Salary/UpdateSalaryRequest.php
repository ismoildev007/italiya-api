<?php

namespace App\Http\Requests\Salary;

use App\Http\Requests\BaseRequest;

class UpdateSalaryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'bonus' => 'nullable|numeric|min:0',
            'deduction' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:255',
        ];
    }
}
