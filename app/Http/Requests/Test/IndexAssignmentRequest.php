<?php

namespace App\Http\Requests\Test;

use App\Http\Requests\BaseRequest;
use App\Models\TestTemplate;
use App\Support\Level;
use Illuminate\Validation\Rule;

class IndexAssignmentRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'class_id' => 'nullable|exists:school_classes,id',
            'student_id' => 'nullable|exists:students,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'status' => 'nullable|in:active,closed',
            'kind' => ['nullable', Rule::in(TestTemplate::KINDS)],
            'level' => ['nullable', Rule::in(Level::all())],
        ];
    }
}
