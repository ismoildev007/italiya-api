<?php

namespace App\Http\Requests\SchoolClass;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class IndexSchoolClassRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:32',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'grade' => 'nullable|integer|min:1|max:11',
            'level' => ['nullable', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'shift' => 'nullable|integer|in:1,2',
            'class_teacher_id' => 'nullable|exists:teachers,id',
            'is_active' => 'nullable|boolean',
        ];
    }
}
