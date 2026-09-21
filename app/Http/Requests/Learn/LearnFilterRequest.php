<?php

namespace App\Http\Requests\Learn;

use App\Http\Requests\BaseRequest;
use App\Models\TestTemplate;
use App\Support\Level;
use Illuminate\Validation\Rule;

class LearnFilterRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'level' => ['nullable', Rule::in(Level::all())],
            'skill' => ['nullable', Rule::in(array_keys(config('academy.skills')))],
            'kind' => ['nullable', Rule::in(TestTemplate::KINDS)],
            'category' => 'nullable|string|max:100',
            'lang' => ['nullable', Rule::in(array_keys(config('academy.translation_languages')))],
            'shuffle' => 'nullable|boolean',
            'status' => 'nullable|in:active,closed',
        ];
    }
}
