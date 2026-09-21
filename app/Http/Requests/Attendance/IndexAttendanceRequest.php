<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

class IndexAttendanceRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->paginationRules() + $this->periodRules() + [
            'class_id' => 'nullable|exists:school_classes,id',
            'student_id' => 'nullable|exists:students,id',
            'status' => 'nullable|in:present,absent,late,excused',
            'source' => 'nullable|in:manual,device',
            'date' => 'nullable|date',
        ];
    }
}
