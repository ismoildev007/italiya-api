<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Individual chegirma — faqat bitta o'quvchiga tegishli,
 * boshqa o'quvchilarga hech qanday ta'sir qilmaydi.
 */
class StudentDiscount extends BaseModel
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_AMOUNT = 'amount';

    protected $fillable = [
        'school_id', 'student_id', 'type', 'value', 'reason',
        'starts_on', 'ends_on', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** Berilgan summadan chegirma miqdorini hisoblaydi. */
    public function amountFor(float $base): float
    {
        $amount = $this->type === self::TYPE_PERCENT
            ? $base * ((float) $this->value / 100)
            : (float) $this->value;

        return round(min($amount, $base), 2);
    }
}
