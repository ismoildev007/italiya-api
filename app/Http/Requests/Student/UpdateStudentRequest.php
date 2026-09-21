<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasUserRules;
use App\Support\Level;
use Illuminate\Validation\Rule;

class UpdateStudentRequest extends BaseRequest
{
    use HasUserRules;

    public function rules(): array
    {
        return $this->nameRules('sometimes') + [
            'student_no' => ['sometimes', 'string', 'max:32', Rule::unique('students', 'student_no')->ignore($this->route('student')?->id)],
            'class_id' => 'nullable|exists:school_classes,id',
            'level' => ['nullable', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'monthly_hours' => 'nullable|integer|min:0|max:400',
            'preferred_language' => ['nullable', Rule::in(array_keys(config('academy.translation_languages')))],
            'parent_phone' => 'nullable|string|max:32',
            'parent_firstname' => 'nullable|string|max:100',
            'parent_lastname' => 'nullable|string|max:100',
            'parent_relation' => 'nullable|in:father,mother,grandparent,other',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'phone' => 'nullable|string|max:32',
            'address' => 'nullable|string|max:255',
            'enrolled_at' => 'nullable|date',
            'status' => 'nullable|in:active,graduated,transferred,expelled',
            'notes' => 'nullable|string',
            'face_id' => 'nullable|string|max:128',
            'card_id' => 'nullable|string|max:128',
        ];
    }
}
