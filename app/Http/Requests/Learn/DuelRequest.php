<?php

namespace App\Http\Requests\Learn;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class DuelRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'opponent_student_id' => 'nullable|exists:students,id',
            'level' => ['nullable', Rule::in(Level::all())],
            'bot_level' => ['nullable', Rule::in(array_keys(config('academy.duel_bots')))],
            'code' => 'nullable|string|max:8',
            'no' => 'nullable|integer|min:1',
            'answer' => 'nullable|string|max:500',
            'q' => 'nullable|string|max:60',
        ];
    }
}
