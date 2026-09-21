<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameSession extends BaseModel
{
    protected $fillable = [
        'school_id', 'game_id', 'student_id', 'started_at', 'finished_at', 'total_rounds', 'correct', 'wrong',
        'score', 'duration_seconds', 'rounds', 'details', 'source',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'rounds' => 'array',
            'details' => 'array',
            'score' => 'integer',
            'correct' => 'integer',
            'wrong' => 'integer',
            'total_rounds' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }
}
