<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends BaseModel
{
    public const STATUS_PLANNED = 'planned';

    public const STATUS_CONDUCTED = 'conducted';

    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'school_id', 'academic_year_id', 'class_id', 'subject_id', 'teacher_id',
        'date', 'start_time', 'end_time', 'topic', 'duration_hours', 'status',
    ];

    protected function casts(): array
    {
        return ['date' => 'date', 'duration_hours' => 'decimal:2'];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function scopeConducted($query)
    {
        return $query->where('status', self::STATUS_CONDUCTED);
    }
}
