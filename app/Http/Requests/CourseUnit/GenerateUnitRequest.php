<?php

namespace App\Http\Requests\CourseUnit;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class GenerateUnitRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'skill' => ['required', Rule::in(array_keys(config('academy.skills')))],
            'topic' => 'nullable|string|max:255',
            'translation_language' => ['nullable', Rule::in(array_keys(config('academy.translation_languages')))],
        ];
    }
}
