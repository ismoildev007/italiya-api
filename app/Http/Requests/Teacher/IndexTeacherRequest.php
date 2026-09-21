<?php

namespace App\Http\Requests\Teacher;

use App\Http\Requests\BaseRequest;

class IndexTeacherRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'search' => 'nullable|string|max:100',
            'specialization' => 'nullable|string|max:150',
            'salary_type' => 'nullable|in:fixed,per_lesson',
            'is_active' => 'nullable|boolean',
        ];
    }
}
