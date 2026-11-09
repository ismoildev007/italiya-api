<?php

namespace App\Models;

use App\Support\YouTube;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Darslik birligi: daraja × yo'nalish × ko'nikma. Audio/video materiali
 * `max_plays` marta tinglanadi, keyin yopiladi (unit_progress.plays orqali).
 * Kunlik dars: PDF (`pdf_path`) majburiy, `available_on` — o'quvchiga ochilish sanasi.
 */
class CourseUnit extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'subject_id', 'teacher_id', 'level', 'track', 'order', 'skill', 'title', 'description',
        'content', 'media_script', 'media_path', 'media_url', 'pdf_path', 'available_on', 'max_plays', 'time_limit_minutes',
        'test_template_id', 'is_published', 'generated_by', 'ai_model', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'available_on' => 'date'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function practiceTest(): BelongsTo
    {
        return $this->belongsTo(TestTemplate::class, 'test_template_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UnitProgress::class);
    }

    public function hasMedia(): bool
    {
        return filled($this->media_path) || filled($this->media_url);
    }

    /** null = cheksiz */
    public function playsLimit(): ?int
    {
        return $this->max_plays ?? config('academy.defaults.unit_max_plays');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * O'quvchiga ochiq darsliklar: ochilish sanasi bugun yoki undan oldin
     * (sanasiz eski darsliklar ham ko'rinadi). Kelajakdagi darslar yashirin.
     */
    public function scopeAvailable($query)
    {
        return $query->where(fn ($q) => $q->whereNull('available_on')->orWhereDate('available_on', '<=', today()));
    }

    public function isAvailable(): bool
    {
        return $this->available_on === null || $this->available_on->lte(today()->endOfDay());
    }

    public function hasPdf(): bool
    {
        return filled($this->pdf_path);
    }

    /** media_url YouTube bo'lsa video id'si (frontend iframe uchun) */
    public function youtubeId(): ?string
    {
        return YouTube::id($this->media_url);
    }

    public function scopeForLevel($query, ?string $level, ?string $track = null)
    {
        return $query->when($level, fn ($q) => $q->where('level', $level))
            ->when($track, fn ($q) => $q->whereIn('track', array_unique([$track, 'general'])));
    }
}
