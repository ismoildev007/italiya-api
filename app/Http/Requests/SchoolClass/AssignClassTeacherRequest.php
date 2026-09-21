<?php

namespace App\Http\Requests\SchoolClass;

use App\Http\Requests\BaseRequest;

class AssignClassTeacherRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['teacher_id' => 'required|exists:teachers,id'];
    }
}
