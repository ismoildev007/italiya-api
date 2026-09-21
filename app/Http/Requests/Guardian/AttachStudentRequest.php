<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\BaseRequest;

class AttachStudentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'relation' => 'required|in:father,mother,grandparent,other',
            'is_primary' => 'boolean',
        ];
    }
}
