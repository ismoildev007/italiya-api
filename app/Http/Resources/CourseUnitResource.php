<?php

namespace App\Http\Resources;

use App\Support\Feature;
use Illuminate\Http\Request;

class CourseUnitResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $progress = $this->relationLoaded('studentProgress') ? $this->getRelation('studentProgress') : null;

        return [
            'id' => $this->id,
            'level' => $this->level,
            'track' => $this->track,
            'order' => $this->order,
            'skill' => $this->skill,
            'skill_label' => config("academy.skills.{$this->skill}"),
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'media_script' => $this->media_script,
            'media_url' => $this->fileUrl($this->media_path) ?: $this->media_url,
            'has_media' => $this->hasMedia(),
            'tts_text' => Feature::audio() && ! $this->hasMedia() ? $this->media_script : null,
            'max_plays' => $this->max_plays,
            'effective_max_plays' => $this->playsLimit(),
            'time_limit_minutes' => $this->time_limit_minutes,
            'test_template_id' => $this->test_template_id,
            'practice_test' => new TestTemplateResource($this->whenLoaded('practiceTest')),
            'is_published' => (bool) $this->is_published,
            'generated_by' => $this->generated_by,
            'ai_model' => $this->ai_model,
            'materials_count' => $this->whenCounted('materials'),
            'materials' => CourseMaterialResource::collection($this->whenLoaded('materials')),
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'play_state' => $this->when($this->getAttribute('play_state') !== null, $this->getAttribute('play_state')),
            'progress' => $this->when($this->relationLoaded('studentProgress'), fn () => $progress ? [
                'plays' => $progress->plays,
                'views' => $progress->views,
                'completed_at' => $this->datetime($progress->completed_at),
                'score' => $progress->score,
            ] : null),
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
