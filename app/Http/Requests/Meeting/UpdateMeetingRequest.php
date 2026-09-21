<?php

namespace App\Http\Requests\Meeting;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class UpdateMeetingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'teacher_id' => 'nullable|exists:teachers,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'platform' => 'nullable|in:zoom,google_meet,other',
            'link' => 'sometimes|url|max:255',
            'starts_at' => 'sometimes|date',
            'duration_minutes' => 'nullable|integer|min:10|max:480',
            'class_ids' => 'nullable|array',
            'class_ids.*' => 'exists:school_classes,id',
            'levels' => 'nullable|array',
            'levels.*' => Rule::in(Level::all()),
            'tracks' => 'nullable|array',
            'tracks.*' => Rule::in(array_keys(config('academy.tracks'))),
            'status' => 'nullable|in:scheduled,live,finished,canceled',
        ];
    }
}
