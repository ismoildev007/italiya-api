<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Test butun sinfga (`target = class`) yoki bitta o'quvchiga (`target = student`)
 * biriktiriladi — o'qituvchi har safar o'zi tanlaydi.
 */
class TestAssignment extends BaseModel
{
    public const TARGET_CLASS = 'class';

    public const TARGET_STUDENT = 'student';

    protected $fillable = [
        'school_id', 'test_template_id', 'class_id', 'student_id', 'teacher_id',
        'target', 'starts_at', 'due_at', 'status',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'due_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TestTemplate::class, 'test_template_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }
}
