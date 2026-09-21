<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Lug'at to'plami — daraja va yo'nalish bo'yicha kartochkalar. */
class VocabDeck extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'teacher_id', 'title', 'level', 'track', 'category', 'language', 'description',
        'cards_count', 'is_published', 'generated_by', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'cards_count' => 'integer'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(VocabCard::class)->orderBy('order');
    }

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function recount(): void
    {
        $this->forceFill(['cards_count' => $this->cards()->count()])->save();
    }
}
