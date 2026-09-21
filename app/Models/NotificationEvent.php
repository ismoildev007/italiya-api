<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Telegram bot integratsiyasi uchun hodisa navbati (stub).
 * Hodisa yaratiladi, keyinchalik bot/queue job uni o'qib xabar yuboradi.
 */
class NotificationEvent extends BaseModel
{
    public const TYPE_ATTENDANCE = 'attendance_marked';

    public const TYPE_GRADE = 'grade_created';

    public const TYPE_PAYMENT = 'payment_received';

    public const TYPE_DEBT = 'debt_reminder';

    public const TYPE_TEST_ASSIGNED = 'test_assigned';

    public const TYPE_HOMEWORK_ASSIGNED = 'homework_assigned';

    public const TYPE_HOMEWORK_GRADED = 'homework_graded';

    public const TYPE_MONTHLY_REPORT = 'monthly_report';

    public const TYPE_CERTIFICATE = 'certificate_issued';

    public const TYPE_MEETING = 'meeting_scheduled';

    public const TYPE_LEVEL_CHANGED = 'level_changed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'school_id', 'type', 'channel', 'recipient_type', 'recipient_id', 'recipient_address',
        'student_id', 'source_type', 'source_id', 'title', 'message', 'payload',
        'status', 'attempts', 'sent_at', 'error',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'sent_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
