<?php

namespace App\Http\Requests\Grade;

use App\Http\Requests\BaseRequest;

class UpdateGradeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'value' => 'sometimes|numeric|min:0|max:100',
            'type' => 'sometimes|in:daily,homework,test,quarter,exam,annual',
            'date' => 'sometimes|date',
            'comment' => 'nullable|string|max:255',
        ];
    }
}
