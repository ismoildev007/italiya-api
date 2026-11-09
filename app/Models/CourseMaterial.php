<?php

namespace App\Models;

use App\Support\YouTube;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Guruh kutubxonasi: video darslik, kitob, fayl, havola. */
class CourseMaterial extends BaseModel
{
    use SoftDeletes;

    public const TYPES = ['video', 'audio', 'file', 'book', 'link'];

    protected $fillable = [
        'school_id', 'course_unit_id', 'class_id', 'level', 'track', 'type', 'title', 'description',
        'path', 'url', 'mime', 'size', 'max_plays', 'uploaded_by',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(CourseUnit::class, 'course_unit_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /** Havola YouTube bo'lsa video id'si */
    public function youtubeId(): ?string
    {
        return $this->path ? null : YouTube::id($this->url);
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->path ? asset('storage/'.$this->path) : $this->url;
    }
}
