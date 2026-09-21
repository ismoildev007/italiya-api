<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\BaseRequest;

class MonthlyReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'year' => 'nullable|integer|min:2020|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
            'student_id' => 'nullable|exists:students,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'with_ai' => 'boolean',
        ];
    }
}
