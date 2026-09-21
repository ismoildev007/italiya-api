<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasUserRules;
use App\Support\Level;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends BaseRequest
{
    use HasUserRules;

    public function rules(): array
    {
        return $this->nameRules() + [
            'student_no' => 'nullable|string|max:32|unique:students,student_no',
            'class_id' => 'nullable|exists:school_classes,id',
            'level' => ['nullable', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'monthly_hours' => 'nullable|integer|min:0|max:400',
            'preferred_language' => ['nullable', Rule::in(array_keys(config('academy.translation_languages')))],
            // Ota-ona: telefon raqami kiritiladi — mavjud bo'lsa bog'lanadi, bo'lmasa yaratiladi
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
            // Kelajakdagi Face ID / turniket uchun (hozircha ixtiyoriy)
            'face_id' => 'nullable|string|max:128',
            'card_id' => 'nullable|string|max:128',
            // O'quvchi akkaunti (faqat o'qish huquqi)
            'create_account' => 'boolean',
            'password' => 'nullable|string|min:6',
        ];
    }
}
