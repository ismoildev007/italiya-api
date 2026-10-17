<?php

namespace App\Http\Requests\Learn;

use App\Http\Requests\BaseRequest;

class LessonAnswerRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'no' => 'required|integer|min:1',
            'answer' => 'nullable',
        ];
    }
}
