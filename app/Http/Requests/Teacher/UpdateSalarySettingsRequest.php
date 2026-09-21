<?php

namespace App\Http\Requests\Teacher;

use App\Http\Requests\BaseRequest;

class UpdateSalarySettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'salary_type' => 'required|in:fixed,per_lesson',
            'fixed_salary' => 'required_if:salary_type,fixed|numeric|min:0',
            'per_lesson_rate' => 'required_if:salary_type,per_lesson|numeric|min:0',
        ];
    }
}
