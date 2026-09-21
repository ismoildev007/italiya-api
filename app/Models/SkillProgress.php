<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Daraja × ko'nikma bo'yicha progress (mockupdagi skill barlar). */
class SkillProgress extends BaseModel
{
    protected $table = 'skill_progress';

    protected $fillable = [
        'school_id', 'student_id', 'level', 'skill', 'percent', 'points', 'attempts_count', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return ['percent' => 'float', 'points' => 'integer', 'attempts_count' => 'integer', 'last_activity_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
