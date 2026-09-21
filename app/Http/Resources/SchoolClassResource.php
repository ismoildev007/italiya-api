<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SchoolClassResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'level_title' => $this->level_title,
            'track' => $this->track,
            'track_title' => $this->track_title,
            'grade' => $this->grade,
            'letter' => $this->letter,
            'shift' => $this->shift,
            'shift_label' => $this->shift_label,
            'schedule_days' => $this->schedule_days,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'zoom_link' => $this->zoom_link,
            'starts_on' => $this->date($this->starts_on),
            'description' => $this->description,
            'room' => $this->room,
            'capacity' => $this->capacity,
            'is_active' => (bool) $this->is_active,
            'students_count' => $this->whenCounted('students'),
            'academic_year' => new AcademicYearResource($this->whenLoaded('academicYear')),
            'class_teacher' => new TeacherResource($this->whenLoaded('classTeacher')),
            'subject_teachers' => ClassSubjectTeacherResource::collection($this->whenLoaded('subjectTeachers')),
        ];
    }
}
