<?php

namespace App\Http\Requests\Teacher;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasUserRules;

class StoreTeacherRequest extends BaseRequest
{
    use HasUserRules;

    public function rules(): array
    {
        return $this->nameRules() + [
            'username' => ['required', ...$this->usernameRule()],
            'phone' => $this->phoneRule(),
            'email' => $this->emailRule(),
            'password' => 'required|string|min:6',
            'employee_no' => 'nullable|string|max:32',
            'specialization' => 'nullable|string|max:150',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'address' => 'nullable|string|max:255',
            'passport_number' => 'nullable|string|max:32',
            'hired_at' => 'nullable|date',
            // Ish haqi rejimi: fiksed oylik yoki dars soniga qarab
            'salary_type' => 'required|in:fixed,per_lesson',
            'fixed_salary' => 'required_if:salary_type,fixed|numeric|min:0',
            'per_lesson_rate' => 'required_if:salary_type,per_lesson|numeric|min:0',
            'is_class_teacher' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return $this->usernameMessages();
    }
}
