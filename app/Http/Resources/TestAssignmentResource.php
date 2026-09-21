<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TestAssignmentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target' => $this->target,
            'status' => $this->status,
            'starts_at' => $this->datetime($this->starts_at),
            'due_at' => $this->datetime($this->due_at),
            'attempts_count' => $this->whenCounted('attempts'),
            'template' => new TestTemplateResource($this->whenLoaded('template')),
            'class' => new SchoolClassResource($this->whenLoaded('schoolClass')),
            'student' => new StudentResource($this->whenLoaded('student')),
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
        ];
    }
}
