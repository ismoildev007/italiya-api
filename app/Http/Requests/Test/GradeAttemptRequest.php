<?php

namespace App\Http\Requests\Test;

use App\Http\Requests\BaseRequest;

/**
 * Ochiq va ovozli javoblarni o'qituvchi qo'lda baholaydi.
 */
class GradeAttemptRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'scores' => 'required|array|min:1',
            'scores.*.answer_id' => 'required|exists:test_answers,id',
            'scores.*.score' => 'required|numeric|min:0',
            'scores.*.is_correct' => 'nullable|boolean',
            'scores.*.feedback' => 'nullable|string|max:500',
        ];
    }
}
