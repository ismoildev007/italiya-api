<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseRequest;
use Illuminate\Support\Carbon;

/**
 * Sinf jurnalini to'plamli saqlash — "tez belgilash" interfeysi.
 */
class BulkAttendanceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'date' => 'required|date',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|exists:students,id',
            'records.*.status' => 'required|in:present,absent,late,excused',
            'records.*.note' => 'nullable|string|max:255',
        ];
    }

    public function attendanceDate(): Carbon
    {
        return Carbon::parse($this->input('date'));
    }

    /** @return array<int, array{student_id:int, status:string, note?:string}> */
    public function records(): array
    {
        return $this->validated()['records'];
    }
}
