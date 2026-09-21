<?php

namespace App\Http\Requests\Teacher;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasUserRules;

class UpdateTeacherRequest extends BaseRequest
{
    use HasUserRules;

    public function rules(): array
    {
        $userId = $this->route('teacher')?->user_id;

        return $this->nameRules('sometimes') + [
            'phone' => $this->phoneRule($userId),
            'email' => $this->emailRule($userId),
            'employee_no' => 'nullable|string|max:32',
            'specialization' => 'nullable|string|max:150',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'address' => 'nullable|string|max:255',
            'passport_number' => 'nullable|string|max:32',
            'hired_at' => 'nullable|date',
            'salary_type' => 'sometimes|in:fixed,per_lesson',
            'fixed_salary' => 'nullable|numeric|min:0',
            'per_lesson_rate' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
        ];
    }
}
