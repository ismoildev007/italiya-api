<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ota-ona. Telegram botga bog'lash `link_code` yoki telefon orqali amalga oshiriladi.
 */
class Guardian extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'user_id', 'firstname', 'lastname', 'middlename', 'phone',
        'preferred_language', 'workplace', 'passport_number', 'telegram_chat_id', 'link_code', 'linked_at',
    ];

    protected function casts(): array
    {
        return ['linked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')
            ->withPivot('relation', 'is_primary')
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->lastname} {$this->firstname} ".($this->middlename ?? ''));
    }

    public function isLinked(): bool
    {
        return ! empty($this->telegram_chat_id);
    }
}
