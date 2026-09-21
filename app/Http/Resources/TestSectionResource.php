<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TestSectionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => $this->order,
            'skill' => $this->skill,
            'skill_label' => config("academy.skills.{$this->skill}"),
            'title' => $this->title,
            'instructions' => $this->instructions,
            'passage' => $this->passage,
            'media_script' => $this->media_script,
            'media_path' => $this->media_path,
            'media_url' => $this->fileUrl($this->media_path) ?: $this->media_url,
            'max_plays' => $this->max_plays,
            'time_limit_minutes' => $this->time_limit_minutes,
            'effective_time_limit' => $this->timeLimit(),
            'ai_generated' => (bool) $this->ai_generated,
            'questions' => TestQuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
