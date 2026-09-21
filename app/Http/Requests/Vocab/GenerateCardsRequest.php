<?php

namespace App\Http\Requests\Vocab;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class GenerateCardsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'topic' => 'nullable|string|max:255',
            'count' => 'nullable|integer|min:3|max:50',
            'languages' => 'nullable|array',
            'languages.*' => Rule::in(array_keys(config('academy.translation_languages'))),
        ];
    }
}
