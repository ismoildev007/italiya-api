<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Oy oxirida ota-onaga yuboriladigan o'zlashtirish tahlili. */
class MonthlyReport extends BaseModel
{
    protected $fillable = ['school_id', 'student_id', 'year', 'month', 'data', 'summary', 'sent_at'];

    protected function casts(): array
    {
        return ['data' => 'array', 'sent_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
