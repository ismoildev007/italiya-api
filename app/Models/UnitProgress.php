<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitProgress extends BaseModel
{
    protected $table = 'unit_progress';

    protected $fillable = [
        'school_id', 'course_unit_id', 'student_id', 'plays', 'views', 'first_viewed_at',
        'last_viewed_at', 'completed_at', 'score',
    ];

    protected function casts(): array
    {
        return [
            'first_viewed_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'score' => 'float',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CourseUnit::class, 'course_unit_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
