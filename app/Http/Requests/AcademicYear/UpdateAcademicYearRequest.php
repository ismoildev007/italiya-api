<?php

namespace App\Http\Requests\AcademicYear;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateAcademicYearRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:32', Rule::unique('academic_years', 'name')->ignore($this->route('academicYear')?->id)],
            'starts_on' => 'sometimes|date',
            'ends_on' => 'sometimes|date|after:starts_on',
            'is_active' => 'boolean',
        ];
    }
}
