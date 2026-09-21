<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ish haqi rejimi har bir o'qituvchi uchun alohida:
 * `fixed` — oylik maosh, `per_lesson` — bir dars narxi × o'tkazilgan darslar.
 */
class Teacher extends BaseModel
{
    use SoftDeletes;

    public const SALARY_FIXED = 'fixed';

    public const SALARY_PER_LESSON = 'per_lesson';

    protected $fillable = [
        'school_id', 'user_id', 'employee_no', 'specialization', 'birth_date',
        'gender', 'address', 'passport_number', 'hired_at',
        'salary_type', 'fixed_salary', 'per_lesson_rate', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'hired_at' => 'date',
            'fixed_salary' => 'decimal:2',
            'per_lesson_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Sinf rahbari bo'lgan sinflari */
    public function supervisedClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'class_teacher_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ClassSubjectTeacher::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(TeacherSalary::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->user?->full_name ?? '';
    }
}
