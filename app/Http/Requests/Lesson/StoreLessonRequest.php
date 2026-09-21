<?php

namespace App\Http\Requests\Lesson;

use App\Http\Requests\BaseRequest;

class StoreLessonRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'class_id' => 'required|exists:school_classes,id',
            'subject_id' => 'required|exists:subjects,id',
            'teacher_id' => 'required|exists:teachers,id',
            'date' => 'required|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'topic' => 'nullable|string|max:255',
            'duration_hours' => 'nullable|numeric|min:0.25|max:8',
            'status' => 'nullable|in:planned,conducted,canceled',
        ];
    }
}
