<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * O'qituvchi tahrirlab yakunlagan test shabloni — qayta ishlatish mumkin.
 */
class TestTemplate extends BaseModel
{
    use SoftDeletes;

    public const SOURCE_AI = 'ai';

    public const SOURCE_MANUAL = 'manual';

    /** Test turlari — urinish va eshitish limitlari config('academy.defaults.kinds') dan */
    public const KIND_PRACTICE = 'practice';

    public const KIND_HOMEWORK = 'homework';

    public const KIND_TEST = 'test';

    public const KIND_MOCK = 'mock';

    public const KIND_PLACEMENT = 'placement';

    public const KINDS = [self::KIND_PRACTICE, self::KIND_HOMEWORK, self::KIND_TEST, self::KIND_MOCK, self::KIND_PLACEMENT];

    protected $fillable = [
        'school_id', 'subject_id', 'teacher_id', 'grade', 'level', 'track', 'kind', 'skill', 'language',
        'title', 'topic', 'description',
        'question_count', 'difficulty', 'generated_by', 'ai_model', 'ai_prompt', 'ai_generated_at',
        'shuffle_questions', 'shuffle_options', 'duration_minutes', 'max_attempts', 'max_plays',
        'auto_grade_ai', 'show_answers', 'pass_percent', 'time_limits', 'course_unit_id', 'total_score',
        'is_published', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'auto_grade_ai' => 'boolean',
            'show_answers' => 'boolean',
            'is_published' => 'boolean',
            'total_score' => 'decimal:2',
            'time_limits' => 'array',
            'ai_generated_at' => 'datetime',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TestSection::class)->orderBy('order');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CourseUnit::class, 'course_unit_id');
    }

    public function homeworks(): HasMany
    {
        return $this->hasMany(Homework::class);
    }

    /** Bo'limlarda ishlatilgan ko'nikmalar ro'yxati */
    public function skills(): array
    {
        $fromSections = $this->relationLoaded('sections')
            ? $this->sections->pluck('skill')
            : $this->sections()->pluck('skill');

        $skills = $fromSections->filter()->unique()->values()->all();

        return $skills ?: array_values(array_filter([$this->skill]));
    }

    /** Faqat variantli savollardan iboratmi (Telegram bot uchun) */
    public function isChoiceOnly(): bool
    {
        return ! $this->questions()->whereNotIn('type', TestQuestion::CHOICE_TYPES)->exists();
    }

    /** Urinishlar limiti: shablon → tur bo'yicha standart */
    public function attemptsLimit(): ?int
    {
        return $this->max_attempts ?? config("academy.defaults.kinds.{$this->kind}.max_attempts");
    }

    /** Eshitish limiti: shablon → tur bo'yicha standart */
    public function playsLimit(): ?int
    {
        return $this->max_plays ?? config("academy.defaults.kinds.{$this->kind}.max_plays");
    }

    public function sectionTimeLimit(string $skill): ?int
    {
        return $this->time_limits[$skill] ?? config("academy.defaults.section_minutes.{$skill}");
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TestQuestion::class)->orderBy('order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TestAssignment::class);
    }

    public function recalculateScore(): void
    {
        $this->forceFill([
            'question_count' => $this->questions()->count(),
            'total_score' => (float) $this->questions()->sum('score'),
        ])->save();
    }
}
