<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Zoom / Google Meet onlayn darslar — guruhlar, darajalar yoki yo'nalishlar bo'yicha. */
class Meeting extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'school_id', 'teacher_id', 'title', 'description', 'platform', 'link', 'starts_at',
        'duration_minutes', 'levels', 'tracks', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'levels' => 'array', 'tracks' => 'array', 'duration_minutes' => 'integer'];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'meeting_class', 'meeting_id', 'class_id');
    }

    /** O'quvchi shu mitingga kira oladimi (guruhi, darajasi yoki yo'nalishi bo'yicha) */
    public function isVisibleTo(Student $student): bool
    {
        if ($this->relationLoaded('groups') ? $this->groups->contains('id', $student->class_id) : $this->groups()->where('class_id', $student->class_id)->exists()) {
            return true;
        }

        if (in_array($student->effectiveLevel(), $this->levels ?? [], true)) {
            return true;
        }

        return in_array($student->effectiveTrack(), $this->tracks ?? [], true);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now()->subHours(2))->whereIn('status', ['scheduled', 'live']);
    }
}
