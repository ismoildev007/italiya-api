<?php

namespace App\Http\Requests\CourseUnit;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class IndexUnitRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'search' => 'nullable|string|max:100',
            'level' => ['nullable', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'skill' => ['nullable', Rule::in(array_keys(config('academy.skills')))],
            'teacher_id' => 'nullable|exists:teachers,id',
            'is_published' => 'nullable|boolean',
        ];
    }
}
