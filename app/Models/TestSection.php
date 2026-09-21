<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * IELTS uslubidagi test bo'limi: Listening / Reading / Writing / Speaking …
 * Reading bo'limida `passage`, Listening bo'limida `media_script` + `media_path`.
 */
class TestSection extends BaseModel
{
    protected $fillable = [
        'school_id', 'test_template_id', 'order', 'skill', 'title', 'instructions', 'passage',
        'media_script', 'media_path', 'media_url', 'max_plays', 'time_limit_minutes', 'ai_generated',
    ];

    protected function casts(): array
    {
        return ['ai_generated' => 'boolean'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TestTemplate::class, 'test_template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TestQuestion::class)->orderBy('order');
    }

    public function hasMedia(): bool
    {
        return filled($this->media_path) || filled($this->media_url);
    }

    /** Bo'lim limiti → shablon limiti → tur bo'yicha standart */
    public function playsLimit(): ?int
    {
        return $this->max_plays ?? $this->template?->playsLimit();
    }

    public function timeLimit(): ?int
    {
        return $this->time_limit_minutes ?? $this->template?->sectionTimeLimit($this->skill);
    }
}
