<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestAttempt extends BaseModel
{
    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_GRADED = 'graded';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'school_id', 'test_assignment_id', 'student_id', 'attempt_no', 'started_at', 'expires_at', 'submitted_at',
        'status', 'question_order', 'section_progress', 'total_score', 'max_score', 'percent', 'ai_summary',
        'source', 'graded_by', 'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'question_order' => 'array',
            'section_progress' => 'array',
            'ai_summary' => 'array',
            'total_score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'percent' => 'decimal:2',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TestAssignment::class, 'test_assignment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TestAnswer::class);
    }

    public function mediaPlays(): HasMany
    {
        return $this->hasMany(MediaPlay::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_GRADED, self::STATUS_EXPIRED], true);
    }
}
