<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSubjectTeacher extends BaseModel
{
    protected $table = 'class_subject_teacher';

    protected $fillable = ['school_id', 'class_id', 'subject_id', 'teacher_id', 'hours_per_week'];

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
}
