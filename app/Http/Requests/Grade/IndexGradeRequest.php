<?php

namespace App\Http\Requests\Grade;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

class IndexGradeRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->paginationRules() + $this->periodRules() + [
            'student_id' => 'nullable|exists:students,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'type' => 'nullable|in:daily,homework,test,quarter,exam,annual',
        ];
    }
}
