<?php

namespace App\Http\Requests\Grade;

use App\Http\Requests\BaseRequest;

class StoreGradeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'value' => 'required|numeric|min:0|max:100',
            'max_value' => 'nullable|numeric|min:1|max:100',
            'type' => 'required|in:daily,homework,test,quarter,exam,annual',
            'date' => 'required|date',
            'comment' => 'nullable|string|max:255',
        ];
    }
}
