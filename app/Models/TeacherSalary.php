<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherSalary extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'school_id', 'teacher_id', 'period_year', 'period_month', 'salary_type',
        'base_amount', 'lessons_count', 'per_lesson_rate', 'bonus', 'deduction',
        'total_amount', 'status', 'paid_at', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'per_lesson_rate' => 'decimal:2',
            'bonus' => 'decimal:2',
            'deduction' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function getPeriodLabelAttribute(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }
}
