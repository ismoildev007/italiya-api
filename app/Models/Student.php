<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'user_id', 'class_id', 'level', 'track', 'monthly_hours', 'preferred_language',
        'telegram_chat_id', 'device_id', 'device_bound_at', 'speech_game_score', 'streak_days',
        'last_activity_at', 'student_no', 'firstname', 'lastname',
        'middlename', 'birth_date', 'gender', 'phone', 'address', 'photo',
        'face_id', 'card_id', 'enrolled_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'enrolled_at' => 'date',
            'device_bound_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'monthly_hours' => 'integer',
            'speech_game_score' => 'integer',
            'streak_days' => 'integer',
        ];
    }

    /** Guruh — `schoolClass` ning o'quv markazidagi nomi */
    public function group(): BelongsTo
    {
        return $this->schoolClass();
    }

    public function skillProgress(): HasMany
    {
        return $this->hasMany(SkillProgress::class);
    }

    public function unitProgress(): HasMany
    {
        return $this->hasMany(UnitProgress::class);
    }

    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    public function homeworkSubmissions(): HasMany
    {
        return $this->hasMany(HomeworkSubmission::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function monthlyReports(): HasMany
    {
        return $this->hasMany(MonthlyReport::class);
    }

    public function levelHistory(): HasMany
    {
        return $this->hasMany(StudentLevelHistory::class);
    }

    /** O'quvchining amaldagi darajasi — o'zida bo'lmasa guruhidan olinadi. */
    public function effectiveLevel(): ?string
    {
        return $this->level ?: $this->schoolClass?->level;
    }

    public function effectiveTrack(): string
    {
        return $this->track ?: ($this->schoolClass?->track ?? 'general');
    }

    public function translationLanguage(): string
    {
        $lang = $this->preferred_language ?: config('academy.default_translation_language', 'uz');

        return array_key_exists($lang, config('academy.translation_languages', [])) ? $lang : 'uz';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')
            ->withPivot('relation', 'is_primary')
            ->withTimestamps();
    }

    public function classHistory(): HasMany
    {
        return $this->hasMany(StudentClassHistory::class);
    }

    public function tariffs(): HasMany
    {
        return $this->hasMany(StudentTariff::class);
    }

    public function activeTariff(): HasOne
    {
        return $this->hasOne(StudentTariff::class)->where('is_active', true)->latestOfMany();
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(StudentDiscount::class);
    }

    public function activeDiscount(): HasOne
    {
        return $this->hasOne(StudentDiscount::class)->where('is_active', true)->latestOfMany();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function balance(): HasOne
    {
        return $this->hasOne(StudentBalance::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinanceTransaction::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function testAttempts(): HasMany
    {
        return $this->hasMany(TestAttempt::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->lastname} {$this->firstname} ".($this->middlename ?? ''));
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
