<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

/**
 * Barcha hisobotlar uchun umumiy filtrlar.
 */
class ReportRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->periodRules() + $this->monthRules() + [
            'class_id' => 'nullable|exists:school_classes,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'student_id' => 'nullable|exists:students,id',
            'type' => 'nullable|in:daily,homework,test,quarter,exam,annual',
            'method' => 'nullable|in:cash,card,transfer,other',
        ];
    }
}
