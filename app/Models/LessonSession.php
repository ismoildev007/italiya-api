<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dars yo'lidagi bitta dars sessiyasi (mashqlar + javoblar + XP). */
class LessonSession extends BaseModel
{
    protected $fillable = [
        'school_id', 'student_id', 'level', 'skill', 'course_unit_id', 'exercises', 'answers',
        'total', 'correct', 'xp_earned', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return ['exercises' => 'array', 'answers' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CourseUnit::class, 'course_unit_id');
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }
}
