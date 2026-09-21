<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkSubmission extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CHECKED = 'checked';

    public const STATUS_RETURNED = 'returned';

    protected $fillable = [
        'school_id', 'homework_id', 'student_id', 'text', 'file_path', 'audio_path', 'test_attempt_id',
        'submitted_at', 'status', 'score', 'feedback', 'graded_by', 'graded_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'graded_at' => 'datetime', 'score' => 'float'];
    }

    public function homework(): BelongsTo
    {
        return $this->belongsTo(Homework::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TestAttempt::class, 'test_attempt_id');
    }
}
