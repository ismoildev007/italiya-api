<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\BaseRequest;
use Illuminate\Support\Carbon;

class JournalRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['date' => 'nullable|date'];
    }

    public function attendanceDate(): Carbon
    {
        return $this->filled('date') ? Carbon::parse($this->input('date')) : now();
    }
}
