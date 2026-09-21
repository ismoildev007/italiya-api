<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestQuestion extends BaseModel
{
    public const TYPE_SINGLE = 'single_choice';

    public const TYPE_MULTIPLE = 'multiple_choice';

    public const TYPE_SHORT_TEXT = 'short_text';

    public const TYPE_ESSAY = 'essay';

    /** To'g'ri / Noto'g'ri / Aytilmagan (IELTS reading) — javob: true|false|ng */
    public const TYPE_TRUE_FALSE = 'true_false';

    /** Bo'sh joyni to'ldirish — o'xshashlik chegarasi bilan tekshiriladi */
    public const TYPE_GAP = 'gap';

    /** Diktant — audio eshitib yozish (listening) */
    public const TYPE_DICTATION = 'dictation';

    /** Ovozli javob — talaffuz + AI tahlili (speaking) */
    public const TYPE_SPEAKING = 'speaking';

    public const TYPES = [
        self::TYPE_SINGLE, self::TYPE_MULTIPLE, self::TYPE_TRUE_FALSE, self::TYPE_SHORT_TEXT,
        self::TYPE_GAP, self::TYPE_DICTATION, self::TYPE_ESSAY, self::TYPE_SPEAKING,
    ];

    /** Telegram botda yechish mumkin bo'lgan turlar */
    public const CHOICE_TYPES = [self::TYPE_SINGLE, self::TYPE_MULTIPLE, self::TYPE_TRUE_FALSE];

    /** O'xshashlik (Levenshtein) bilan avtomatik tekshiriladigan turlar */
    public const SIMILARITY_TYPES = [self::TYPE_SHORT_TEXT, self::TYPE_GAP, self::TYPE_DICTATION];

    /** AI (Gemini) tahlili talab qiladigan turlar */
    public const AI_TYPES = [self::TYPE_ESSAY, self::TYPE_SPEAKING];

    /** Javob turi savol darajasida belgilanadi */
    public const MODE_WRITTEN = 'written';

    public const MODE_VOICE = 'voice';

    protected $fillable = [
        'school_id', 'test_template_id', 'test_section_id', 'order', 'body', 'type', 'skill', 'answer_mode',
        'score', 'correct_answer', 'explanation', 'media_script', 'media_path', 'max_plays',
        'similarity_threshold', 'model_answer', 'translations', 'meta', 'ai_generated',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'ai_generated' => 'boolean',
            'similarity_threshold' => 'float',
            'translations' => 'array',
            'meta' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TestTemplate::class, 'test_template_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(TestSection::class, 'test_section_id');
    }

    public function isTrueFalse(): bool
    {
        return $this->type === self::TYPE_TRUE_FALSE;
    }

    public function isSimilarity(): bool
    {
        return in_array($this->type, self::SIMILARITY_TYPES, true) && filled($this->correct_answer);
    }

    public function needsAi(): bool
    {
        return in_array($this->type, self::AI_TYPES, true) || $this->answer_mode === self::MODE_VOICE;
    }

    public function hasMedia(): bool
    {
        return filled($this->media_path);
    }

    /** Standart o'xshashlik chegarasi (mockup: diktant 0.85, gap 0.75) */
    public function threshold(): float
    {
        return (float) ($this->similarity_threshold ?? match ($this->type) {
            self::TYPE_GAP => 0.75,
            self::TYPE_SHORT_TEXT => 0.9,
            default => 0.85,
        });
    }

    public function options(): HasMany
    {
        return $this->hasMany(TestQuestionOption::class)->orderBy('order');
    }

    public function isChoice(): bool
    {
        return in_array($this->type, [self::TYPE_SINGLE, self::TYPE_MULTIPLE], true);
    }

    /** Variant tanlanadigan savol (botda ham yechiladi) */
    public function isSelectable(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }
}
