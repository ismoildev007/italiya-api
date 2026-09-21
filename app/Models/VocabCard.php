<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kartochka: old tomoni italyancha so'z, orqasi — tanlangan tildagi tarjima
 * (`translations` = {"uz": "...", "en": "...", "ru": "..."}).
 */
class VocabCard extends BaseModel
{
    protected $fillable = [
        'school_id', 'vocab_deck_id', 'order', 'word', 'translations', 'category', 'part_of_speech',
        'example', 'example_translations', 'pronunciation', 'audio_path', 'image_path', 'ai_generated',
    ];

    protected function casts(): array
    {
        return ['translations' => 'array', 'example_translations' => 'array', 'ai_generated' => 'boolean'];
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(VocabDeck::class, 'vocab_deck_id');
    }

    /** Tanlangan tildagi tarjima; bo'lmasa o'zbekcha, bo'lmasa birinchisi. */
    public function translation(string $lang = 'uz'): string
    {
        $t = $this->translations ?? [];

        return (string) ($t[$lang] ?? $t['uz'] ?? $t['en'] ?? reset($t) ?: '');
    }

    public function exampleTranslation(string $lang = 'uz'): ?string
    {
        $t = $this->example_translations ?? [];

        return $t[$lang] ?? $t['uz'] ?? null;
    }

    public function getAudioUrlAttribute(): ?string
    {
        return $this->audio_path ? asset('storage/'.$this->audio_path) : null;
    }
}
