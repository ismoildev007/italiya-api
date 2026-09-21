<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * To'liq moliyaviy tarix. Har bir invoice, to'lov, chegirma o'zgarishi
 * shu yerga yoziladi — admin/buxgalter panelida hech narsa yashirin qolmaydi.
 */
class FinanceTransaction extends BaseModel
{
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_DISCOUNT_CHANGE = 'discount_change';

    public const TYPE_TARIFF_CHANGE = 'tariff_change';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_REFUND = 'refund';

    protected $fillable = [
        'school_id', 'student_id', 'type', 'reference_type', 'reference_id',
        'amount', 'balance_after', 'description', 'meta', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
