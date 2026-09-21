<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Grade extends BaseModel
{
    public const TYPES = ['daily', 'homework', 'test', 'quarter', 'exam', 'annual'];

    protected $fillable = [
        'school_id', 'student_id', 'subject_id', 'class_id', 'teacher_id',
        'value', 'max_value', 'type', 'source_type', 'source_id', 'date', 'comment',
    ];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'max_value' => 'decimal:2', 'date' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
