<?php

namespace App\Http\Requests\Certificate;

use App\Http\Requests\BaseRequest;

class IssueCertificateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            // administrator 25-sana qoidasini chetlab o'tishi mumkin (oyiga 1 marta qoidasi saqlanadi)
            'force' => 'boolean',
        ];
    }
}
