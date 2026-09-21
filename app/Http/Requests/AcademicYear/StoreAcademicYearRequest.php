<?php

namespace App\Http\Requests\AcademicYear;

use App\Http\Requests\BaseRequest;

class StoreAcademicYearRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:32|unique:academic_years,name',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date|after:starts_on',
            'is_active' => 'boolean',
        ];
    }
}
