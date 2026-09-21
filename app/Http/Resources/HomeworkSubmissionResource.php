<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class HomeworkSubmissionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'homework_id' => $this->homework_id,
            'text' => $this->text,
            'file_url' => $this->fileUrl($this->file_path),
            'audio_url' => $this->fileUrl($this->audio_path),
            'test_attempt_id' => $this->test_attempt_id,
            'submitted_at' => $this->datetime($this->submitted_at),
            'status' => $this->status,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'graded_at' => $this->datetime($this->graded_at),
            'student' => new StudentResource($this->whenLoaded('student')),
            'homework' => new HomeworkResource($this->whenLoaded('homework')),
        ];
    }
}
