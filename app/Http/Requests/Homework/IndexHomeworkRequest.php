<?php

namespace App\Http\Requests\Homework;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class IndexHomeworkRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'search' => 'nullable|string|max:100',
            'class_id' => 'nullable|exists:school_classes,id',
            'student_id' => 'nullable|exists:students,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'level' => ['nullable', Rule::in(Level::all())],
            'status' => 'nullable|in:active,closed',
        ];
    }
}
