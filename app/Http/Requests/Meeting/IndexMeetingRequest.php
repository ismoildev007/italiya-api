<?php

namespace App\Http\Requests\Meeting;

use App\Http\Requests\BaseRequest;

class IndexMeetingRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'search' => 'nullable|string|max:100',
            'teacher_id' => 'nullable|exists:teachers,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'status' => 'nullable|in:scheduled,live,finished,canceled',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'upcoming' => 'nullable|boolean',
        ];
    }
}
