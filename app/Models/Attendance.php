<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Attendance extends BaseModel
{
    public const STATUS_PRESENT = 'present';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_LATE = 'late';

    public const STATUS_EXCUSED = 'excused';

    /** Yozuv manbai. Hozircha faqat `manual`; `device` kelajakdagi Face ID/turniket uchun. */
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_DEVICE = 'device';

    protected $fillable = [
        'school_id', 'student_id', 'class_id', 'date', 'status',
        'source', 'device_id', 'marked_at', 'marked_by', 'note',
    ];

    protected function casts(): array
    {
        return ['marked_at' => 'datetime'];
    }

    /**
     * `date` doim `Y-m-d` ko'rinishida saqlanadi — shu sababli
     * (student_id, date) bo'yicha unikal indeks va `updateOrCreate`
     * bir kunga faqat bitta yozuv qoldiradi.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->startOfDay() : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
