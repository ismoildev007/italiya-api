<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseRequest;
use Illuminate\Support\Carbon;

class StoreAttendanceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'date' => 'required|date',
            'status' => 'required|in:present,absent,late,excused',
            'note' => 'nullable|string|max:255',
        ];
    }

    public function attendanceDate(): Carbon
    {
        return Carbon::parse($this->input('date'));
    }
}
