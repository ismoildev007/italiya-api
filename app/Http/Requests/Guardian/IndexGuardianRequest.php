<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\BaseRequest;

class IndexGuardianRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'search' => 'nullable|string|max:100',
            'student_id' => 'nullable|exists:students,id',
            'linked' => 'nullable|boolean',
        ];
    }
}
