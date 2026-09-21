<?php

namespace App\Http\Requests\Learn;

use App\Http\Requests\BaseRequest;

class SubmitAttemptRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'answers' => 'nullable|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.selected_option_ids' => 'nullable|array',
            'answers.*.answer_text' => 'nullable|string|max:20000',
            'answers.*.transcript' => 'nullable|string|max:20000',
        ];
    }
}
