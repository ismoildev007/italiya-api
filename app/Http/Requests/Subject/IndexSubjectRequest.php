<?php

namespace App\Http\Requests\Subject;

use App\Http\Requests\BaseRequest;

class IndexSubjectRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ];
    }
}
