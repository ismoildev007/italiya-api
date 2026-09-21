<?php

namespace App\Http\Requests\Homework;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class UpdateHomeworkRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'teacher_id' => 'nullable|exists:teachers,id',
            'class_id' => 'sometimes|nullable|exists:school_classes,id',
            'student_id' => 'sometimes|nullable|exists:students,id',
            'level' => ['nullable', Rule::in(Level::all())],
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|max:51200',
            'test_template_id' => 'nullable|exists:test_templates,id',
            'course_unit_id' => 'nullable|exists:course_units,id',
            'due_at' => 'nullable|date',
            'max_score' => 'nullable|integer|min:1|max:1000',
            'status' => 'nullable|in:active,closed',
        ];
    }
}
