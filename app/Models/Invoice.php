<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends BaseModel
{
    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELED = 'canceled';

    protected $fillable = [
        'school_id', 'student_id', 'tariff_id', 'number', 'period_year', 'period_month',
        'base_amount', 'discount_amount', 'total_amount', 'paid_amount',
        'status', 'due_date', 'generated_at', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'generated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function tariff(): BelongsTo
    {
        return $this->belongsTo(Tariff::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getRemainingAttribute(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }

    public function getPeriodLabelAttribute(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }
}
