<?php

namespace App\Models;

use App\Support\Level;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends BaseModel
{
    use SoftDeletes;

    protected $table = 'school_classes';

    protected $fillable = [
        'school_id', 'academic_year_id', 'name', 'level', 'track', 'grade', 'letter',
        'shift', 'schedule_days', 'start_time', 'end_time', 'class_teacher_id', 'room',
        'zoom_link', 'starts_on', 'description', 'capacity', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'shift' => 'integer',
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'schedule_days' => 'array',
            'starts_on' => 'date',
        ];
    }

    public function homeworks(): HasMany
    {
        return $this->hasMany(Homework::class, 'class_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(CourseMaterial::class, 'class_id');
    }

    public function meetings(): BelongsToMany
    {
        return $this->belongsToMany(Meeting::class, 'meeting_class', 'class_id', 'meeting_id');
    }

    public function getLevelTitleAttribute(): string
    {
        return Level::title($this->level);
    }

    public function getTrackTitleAttribute(): string
    {
        return (string) config("academy.tracks.{$this->track}", $this->track);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** Sinf rahbari — har bir sinfga bitta */
    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'class_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function subjectTeachers(): HasMany
    {
        return $this->hasMany(ClassSubjectTeacher::class, 'class_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'class_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'class_id');
    }

    public function getShiftLabelAttribute(): string
    {
        return config("school.shifts.{$this->shift}.label", "{$this->shift}-smena");
    }
}
