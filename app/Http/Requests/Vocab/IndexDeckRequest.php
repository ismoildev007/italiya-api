<?php

namespace App\Http\Requests\Vocab;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class IndexDeckRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'search' => 'nullable|string|max:100',
            'level' => ['nullable', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'category' => 'nullable|string|max:100',
            'is_published' => 'nullable|boolean',
        ];
    }
}
