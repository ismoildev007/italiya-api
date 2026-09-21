<?php

namespace App\Http\Requests\Test;

use App\Http\Requests\BaseRequest;

/**
 * Testni butun sinfga yoki bitta o'quvchiga biriktirish.
 */
class AssignTestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'class_id' => 'required_without:student_id|nullable|exists:school_classes,id',
            'student_id' => 'required_without:class_id|nullable|exists:students,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'starts_at' => 'nullable|date',
            'due_at' => 'nullable|date|after:starts_at',
        ];
    }
}
