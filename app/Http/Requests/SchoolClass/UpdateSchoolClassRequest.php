<?php

namespace App\Http\Requests\SchoolClass;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class UpdateSchoolClassRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:16',
            'level' => ['sometimes', Rule::in(Level::all())],
            'track' => ['sometimes', Rule::in(array_keys(config('academy.tracks')))],
            'grade' => 'sometimes|integer|min:1|max:11',
            'letter' => 'sometimes|string|max:4',
            'shift' => 'sometimes|integer|in:1,2',
            'schedule_days' => 'nullable|array',
            'schedule_days.*' => 'integer|min:1|max:7',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
            'class_teacher_id' => 'nullable|exists:teachers,id',
            'room' => 'nullable|string|max:32',
            'zoom_link' => 'nullable|url|max:255',
            'starts_on' => 'nullable|date',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1|max:60',
            'is_active' => 'boolean',
        ];
    }
}
