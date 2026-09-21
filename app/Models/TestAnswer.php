<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * O'quvchining har bir savolga bergan aniq javobi to'liq saqlanadi:
 * matn yoki ovozdan transkripsiya qilingan matn + original audio havolasi.
 */
class TestAnswer extends BaseModel
{
    protected $fillable = [
        'school_id', 'test_attempt_id', 'test_question_id', 'selected_option_ids',
        'answer_text', 'audio_path', 'transcript', 'transcription_provider', 'transcribed_at',
        'option_order', 'is_correct', 'similarity', 'plays', 'score', 'feedback',
        'ai_score', 'ai_feedback', 'ai_provider', 'ai_evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'selected_option_ids' => 'array',
            'option_order' => 'array',
            'is_correct' => 'boolean',
            'score' => 'decimal:2',
            'transcribed_at' => 'datetime',
            'similarity' => 'float',
            'plays' => 'integer',
            'ai_score' => 'decimal:2',
            'ai_feedback' => 'array',
            'ai_evaluated_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TestAttempt::class, 'test_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TestQuestion::class, 'test_question_id');
    }

    /** Baholashda ishlatiladigan yakuniy matn (ovozli javobda — transkript). */
    public function effectiveText(): ?string
    {
        return $this->answer_text ?: $this->transcript;
    }
}
