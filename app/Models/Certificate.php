<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Oylik sertifikat: oyiga 1 marta, oyning 25-sanasidan keyin generatsiya qilinadi.
 * Ball = davomat×0.4 + uy ishi×0.3 + test×0.3 (mockup formulasi) + ko'nikmalar kesimi.
 */
class Certificate extends BaseModel
{
    protected $fillable = [
        'school_id', 'student_id', 'serial', 'level', 'track', 'period_year', 'period_month', 'title',
        'attendance_score', 'homework_score', 'test_score', 'overall_score', 'skills', 'grade_label',
        'verify_code', 'issued_at', 'issued_by', 'file_path',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'issued_at' => 'datetime',
            'attendance_score' => 'float',
            'homework_score' => 'float',
            'test_score' => 'float',
            'overall_score' => 'float',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function getPeriodLabelAttribute(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }
}
