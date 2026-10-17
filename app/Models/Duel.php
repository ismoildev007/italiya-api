<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Duel: ikki o'quvchi (yoki o'quvchi va bot) bir xil savollarga javob beradi —
 * raundda kim birinchi TO'G'RI javob bersa, o'sha ochko oladi.
 */
class Duel extends BaseModel
{
    public const WAITING = 'waiting';

    public const ACTIVE = 'active';

    public const FINISHED = 'finished';

    public const CANCELED = 'canceled';

    protected $fillable = [
        'school_id', 'level', 'host_student_id', 'opponent_student_id', 'bot_level', 'invite_code', 'status',
        'questions', 'rounds', 'current_no', 'round_started_at', 'round_seconds', 'host_score', 'opponent_score',
        'winner', 'xp', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'array',
            'rounds' => 'array',
            'xp' => 'array',
            'round_started_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'host_student_id');
    }

    public function opponent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'opponent_student_id');
    }

    public function isBot(): bool
    {
        return $this->bot_level !== null;
    }

    public function sideOf(Student $student): ?string
    {
        return match (true) {
            $student->id === $this->host_student_id => 'host',
            $student->id === $this->opponent_student_id => 'opponent',
            default => null,
        };
    }

    public function isParticipant(Student $student): bool
    {
        return $this->sideOf($student) !== null;
    }
}
