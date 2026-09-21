<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Telegram bot suhbat holati (school scope'siz — chat_id global). */
class TelegramSession extends Model
{
    public const STATE_IDLE = 'idle';

    public const STATE_AWAIT_PHONE = 'await_phone';

    public const STATE_AWAIT_PASSWORD = 'await_password';

    public const STATE_IN_TEST = 'in_test';

    protected $fillable = [
        'bot', 'chat_id', 'user_id', 'student_id', 'guardian_id', 'state', 'context', 'phone', 'language', 'last_message_at',
    ];

    protected function casts(): array
    {
        return ['context' => 'array', 'last_message_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function setState(string $state, array $context = []): self
    {
        $this->forceFill(['state' => $state, 'context' => $context ?: null, 'last_message_at' => now()])->save();

        return $this;
    }
}
