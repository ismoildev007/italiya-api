<?php

namespace App\Http\Requests\Vocab;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

/** Lug'at to'plami + kartochkalar (old: italyancha, orqa: {uz,en,ru}). */
class StoreDeckRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'teacher_id' => 'nullable|exists:teachers,id',
            'title' => 'required|string|max:255',
            'level' => ['required', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'category' => 'nullable|string|max:100',
            'language' => 'nullable|string|size:2',
            'description' => 'nullable|string',
            'is_published' => 'boolean',
            'generated_by' => 'nullable|in:ai,manual,import',
            'cards' => 'sometimes|array',
            'cards.*.id' => 'nullable|integer',
            'cards.*.order' => 'nullable|integer|min:1',
            'cards.*.word' => 'required|string|max:255',
            'cards.*.translations' => 'required_without:cards.*.translation|array',
            'cards.*.translations.*' => 'nullable|string|max:255',
            'cards.*.translation' => 'nullable|string|max:255',
            'cards.*.category' => 'nullable|string|max:100',
            'cards.*.part_of_speech' => 'nullable|string|max:20',
            'cards.*.example' => 'nullable|string',
            'cards.*.example_translations' => 'nullable|array',
            'cards.*.pronunciation' => 'nullable|string|max:255',
            'cards.*.ai_generated' => 'boolean',
        ];
    }
}
