<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Har bir o'quvchi har bir audio/videoni necha marta eshitgani.
 * Test urinishi ichida — attempt bo'yicha alohida hisoblanadi.
 */
class MediaPlay extends BaseModel
{
    protected $fillable = [
        'school_id', 'student_id', 'playable_type', 'playable_id', 'test_attempt_id', 'plays', 'last_played_at',
    ];

    protected function casts(): array
    {
        return ['last_played_at' => 'datetime', 'plays' => 'integer'];
    }

    public function playable(): MorphTo
    {
        return $this->morphTo();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
