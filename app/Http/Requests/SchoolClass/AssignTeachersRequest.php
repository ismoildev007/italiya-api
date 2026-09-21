<?php

namespace App\Http\Requests\SchoolClass;

use App\Http\Requests\BaseRequest;

class AssignTeachersRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'assignments' => 'required|array|min:1',
            'assignments.*.subject_id' => 'required|exists:subjects,id',
            'assignments.*.teacher_id' => 'required|exists:teachers,id',
            'assignments.*.hours_per_week' => 'nullable|integer|min:1|max:10',
        ];
    }
}
