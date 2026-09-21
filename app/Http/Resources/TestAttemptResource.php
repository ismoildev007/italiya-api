<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TestAttemptResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attempt_no' => $this->attempt_no,
            'status' => $this->status,
            'source' => $this->source,
            'started_at' => $this->datetime($this->started_at),
            'expires_at' => $this->datetime($this->expires_at),
            'submitted_at' => $this->datetime($this->submitted_at),
            'graded_at' => $this->datetime($this->graded_at),
            'total_score' => (float) $this->total_score,
            'max_score' => (float) $this->max_score,
            'percent' => (float) $this->percent,
            'question_order' => $this->question_order,
            'section_progress' => $this->section_progress,
            'summary' => $this->ai_summary,
            'student' => new StudentResource($this->whenLoaded('student')),
            'assignment' => new TestAssignmentResource($this->whenLoaded('assignment')),
            'answers' => TestAnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
