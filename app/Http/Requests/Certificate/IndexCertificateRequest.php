<?php

namespace App\Http\Requests\Certificate;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class IndexCertificateRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'student_id' => 'nullable|exists:students,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'level' => ['nullable', Rule::in(Level::all())],
            'period_year' => 'nullable|integer|min:2020|max:2100',
            'period_month' => 'nullable|integer|min:1|max:12',
        ];
    }
}
