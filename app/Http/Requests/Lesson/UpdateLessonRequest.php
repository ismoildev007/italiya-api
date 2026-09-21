<?php

namespace App\Http\Requests\Lesson;

use App\Http\Requests\BaseRequest;

class UpdateLessonRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'date' => 'sometimes|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'topic' => 'nullable|string|max:255',
            'duration_hours' => 'nullable|numeric|min:0.25|max:8',
            'status' => 'sometimes|in:planned,conducted,canceled',
        ];
    }
}
