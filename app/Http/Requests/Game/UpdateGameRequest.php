<?php

namespace App\Http\Requests\Game;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class UpdateGameRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'teacher_id' => 'nullable|exists:teachers,id',
            'vocab_deck_id' => 'nullable|exists:vocab_decks,id',
            'title' => 'sometimes|string|max:255',
            'type' => ['nullable', Rule::in(array_keys(config('academy.games.types')))],
            'level' => ['sometimes', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            // o'yin (savollar) soni
            'card_count' => 'nullable|integer|min:3|max:100',
            'seconds_per_card' => 'nullable|integer|min:5|max:120',
            'round_types' => 'nullable|array',
            'round_types.*' => Rule::in(['it-to-tr', 'tr-to-it', 'voice', 'typing', 'listening']),
            'prompt_language' => 'nullable|string|size:2',
            'answer_language' => ['nullable', Rule::in(array_keys(config('academy.translation_languages')))],
            'points_per_correct' => 'nullable|integer|min:1|max:100',
            'is_active' => 'boolean',
        ];
    }
}
