<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class IndexGameRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'search' => 'nullable|string|max:100',
            'level' => ['nullable', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'type' => ['nullable', Rule::in(array_keys(config('academy.games.types')))],
            'vocab_deck_id' => 'nullable|exists:vocab_decks,id',
            'is_active' => 'nullable|boolean',
        ];
    }
}
