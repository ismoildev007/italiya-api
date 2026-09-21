<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class GradeResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'value' => (float) $this->value,
            'max_value' => (float) $this->max_value,
            'type' => $this->type,
            'date' => $this->date($this->date),
            'comment' => $this->comment,
            'source_type' => $this->source_type ? class_basename($this->source_type) : null,
            'source_id' => $this->source_id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'class' => new SchoolClassResource($this->whenLoaded('schoolClass')),
        ];
    }
}
