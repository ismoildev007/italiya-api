<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class LessonResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date($this->date),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'topic' => $this->topic,
            'duration_hours' => (float) $this->duration_hours,
            'status' => $this->status,
            'class' => new SchoolClassResource($this->whenLoaded('schoolClass')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
        ];
    }
}
