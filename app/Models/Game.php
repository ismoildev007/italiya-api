<?php

namespace App\Models;

use App\Support\Feature;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * O'qituvchi har bir daraja uchun o'yin yaratadi: to'plam, savollar soni,
 * raund turlari (it→uz, uz→it, ovozli) va har bir to'g'ri javob uchun ball.
 */
class Game extends BaseModel
{
    use SoftDeletes;

    public const ROUND_IT_TO_TR = 'it-to-tr';   // italyancha so'z → tarjimani tanlash

    public const ROUND_TR_TO_IT = 'tr-to-it';   // tarjima → italyancha so'zni tanlash

    public const ROUND_VOICE = 'voice';         // so'zni talaffuz qilish

    public const ROUND_TYPING = 'typing';       // tarjimani yozish

    public const ROUND_LISTENING = 'listening'; // audio eshitib yozish

    protected $fillable = [
        'school_id', 'teacher_id', 'vocab_deck_id', 'title', 'type', 'level', 'track', 'card_count',
        'seconds_per_card', 'round_types', 'prompt_language', 'answer_language', 'points_per_correct',
        'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'round_types' => 'array',
            'is_active' => 'boolean',
            'card_count' => 'integer',
            'seconds_per_card' => 'integer',
            'points_per_correct' => 'integer',
        ];
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(VocabDeck::class, 'vocab_deck_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    /** Raund turlari — ko'rsatilmagan bo'lsa o'yin turiga qarab standart */
    public function roundTypes(): array
    {
        if (! empty($this->round_types)) {
            $allowed = array_values(array_intersect($this->round_types, Feature::roundTypes()));

            if ($allowed !== []) {
                return $allowed;
            }
        }

        $types = match ($this->type) {
            'speech' => [self::ROUND_IT_TO_TR, self::ROUND_TR_TO_IT, self::ROUND_VOICE],
            'choice', 'match' => [self::ROUND_IT_TO_TR, self::ROUND_TR_TO_IT],
            'typing' => [self::ROUND_TYPING],
            'listening' => [self::ROUND_LISTENING],
            default => [self::ROUND_IT_TO_TR],
        };

        // Audio o'chirilgan bo'lsa ovozli/eshitish raundlari chiqarib tashlanadi
        $types = array_values(array_intersect($types, Feature::roundTypes()));

        return $types ?: [self::ROUND_IT_TO_TR];
    }
}
