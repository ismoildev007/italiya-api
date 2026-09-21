<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Musbat balans — oldindan to'langan summa (keyingi oyga o'tadi).
 * Manfiy balans — qarzdorlik.
 */
class StudentBalance extends BaseModel
{
    protected $fillable = [
        'school_id', 'student_id', 'balance', 'total_charged', 'total_paid', 'recalculated_at',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'total_charged' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'recalculated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function isDebtor(): bool
    {
        return (float) $this->balance < 0;
    }
}
