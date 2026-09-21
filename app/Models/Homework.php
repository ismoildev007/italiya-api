<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Uy ishi: guruhga yoki bitta o'quvchiga. Test shabloni yoki darslik biriktirilishi mumkin;
 * o'quvchi matn/fayl/audio yuboradi, o'qituvchi 0..100 ball qo'yadi.
 */
class Homework extends BaseModel
{
    use SoftDeletes;

    protected $table = 'homeworks';

    protected $fillable = [
        'school_id', 'teacher_id', 'class_id', 'student_id', 'level', 'title', 'description',
        'attachment_path', 'test_template_id', 'course_unit_id', 'due_at', 'max_score', 'status',
    ];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'max_score' => 'integer'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function testTemplate(): BelongsTo
    {
        return $this->belongsTo(TestTemplate::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CourseUnit::class, 'course_unit_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->due_at->isPast();
    }
}
