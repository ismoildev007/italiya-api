<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class HomeworkResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'level' => $this->level,
            'attachment_url' => $this->fileUrl($this->attachment_path),
            'test_template_id' => $this->test_template_id,
            'course_unit_id' => $this->course_unit_id,
            'due_at' => $this->datetime($this->due_at),
            'is_overdue' => $this->isOverdue(),
            'max_score' => $this->max_score,
            'status' => $this->status,
            'submissions_count' => $this->whenCounted('submissions'),
            'pending_count' => $this->when($this->getAttribute('pending_count') !== null, $this->getAttribute('pending_count')),
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'class' => new SchoolClassResource($this->whenLoaded('schoolClass')),
            'student' => new StudentResource($this->whenLoaded('student')),
            'test' => new TestTemplateResource($this->whenLoaded('testTemplate')),
            'unit' => new CourseUnitResource($this->whenLoaded('unit')),
            'my_submission' => $this->when($this->relationLoaded('mySubmission'), fn () => $this->getRelation('mySubmission') ? new HomeworkSubmissionResource($this->getRelation('mySubmission')) : null),
            'submissions' => HomeworkSubmissionResource::collection($this->whenLoaded('submissions')),
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
