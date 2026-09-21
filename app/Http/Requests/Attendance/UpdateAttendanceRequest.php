<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseRequest;

class UpdateAttendanceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'status' => 'required|in:present,absent,late,excused',
            'note' => 'nullable|string|max:255',
        ];
    }
}
