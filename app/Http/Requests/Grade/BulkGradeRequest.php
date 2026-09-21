<?php

namespace App\Http\Requests\Grade;

use App\Http\Requests\BaseRequest;

/**
 * Sinf uchun to'plamli baholash (jurnal ko'rinishi).
 */
class BulkGradeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'subject_id' => 'required|exists:subjects,id',
            'class_id' => 'required|exists:school_classes,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'date' => 'required|date',
            'type' => 'required|in:daily,homework,test,quarter,exam,annual',
            'grades' => 'required|array|min:1',
            'grades.*.student_id' => 'required|exists:students,id',
            'grades.*.value' => 'required|numeric|min:0|max:100',
            'grades.*.comment' => 'nullable|string|max:255',
        ];
    }
}
