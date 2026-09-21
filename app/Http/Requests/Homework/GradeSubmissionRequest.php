<?php

namespace App\Http\Requests\Homework;

use App\Http\Requests\BaseRequest;

class GradeSubmissionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'score' => 'required|numeric|min:0|max:1000',
            'feedback' => 'nullable|string|max:2000',
            'returned' => 'boolean',
        ];
    }
}
