<?php

namespace App\Http\Requests\SchoolClass;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

/** Guruh (til o'quv markazida "sinf" = guruh): daraja, yo'nalish, jadval, Zoom. */
class StoreSchoolClassRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'name' => 'required|string|max:16',
            'level' => ['required', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'grade' => 'nullable|integer|min:1|max:11',
            'letter' => 'nullable|string|max:4',
            'shift' => 'nullable|integer|in:1,2',
            'schedule_days' => 'nullable|array',
            'schedule_days.*' => 'integer|min:1|max:7',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'class_teacher_id' => 'nullable|exists:teachers,id',
            'room' => 'nullable|string|max:32',
            'zoom_link' => 'nullable|url|max:255',
            'starts_on' => 'nullable|date',
            'description' => 'nullable|string',
            'capacity' => 'nullable|integer|min:1|max:60',
        ];
    }
}
