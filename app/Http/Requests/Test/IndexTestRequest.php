<?php

namespace App\Http\Requests\Test;

use App\Http\Requests\BaseRequest;
use App\Models\TestTemplate;
use App\Support\Level;
use Illuminate\Validation\Rule;

class IndexTestRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'search' => 'nullable|string|max:100',
            'subject_id' => 'nullable|exists:subjects,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'grade' => 'nullable|integer|min:1|max:11',
            'level' => ['nullable', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'kind' => ['nullable', Rule::in(TestTemplate::KINDS)],
            'skill' => ['nullable', Rule::in(array_keys(config('academy.skills')))],
            'course_unit_id' => 'nullable|exists:course_units,id',
            'generated_by' => 'nullable|in:ai,manual',
            'is_published' => 'nullable|boolean',
        ];
    }
}
