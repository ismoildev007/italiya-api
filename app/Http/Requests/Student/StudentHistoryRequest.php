<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

/**
 * O'quvchining davomat/baho tarixi uchun umumiy davr filtri.
 */
class StudentHistoryRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->periodRules() + [
            'subject_id' => 'nullable|exists:subjects,id',
        ];
    }
}
