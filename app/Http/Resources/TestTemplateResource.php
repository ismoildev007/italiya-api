<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TestTemplateResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'topic' => $this->topic,
            'description' => $this->description,
            'level' => $this->level,
            'track' => $this->track,
            'kind' => $this->kind,
            'skill' => $this->skill,
            'skills' => $this->when($this->relationLoaded('sections') || $this->skill, fn () => $this->skills()),
            'language' => $this->language,
            'grade' => $this->grade,
            'difficulty' => $this->difficulty,
            'question_count' => $this->question_count,
            'questions_count' => $this->whenCounted('questions'),
            'total_score' => (float) $this->total_score,
            'duration_minutes' => $this->duration_minutes,
            'max_attempts' => $this->max_attempts,
            'effective_max_attempts' => $this->attemptsLimit(),
            'max_plays' => $this->max_plays,
            'effective_max_plays' => $this->playsLimit(),
            'auto_grade_ai' => (bool) $this->auto_grade_ai,
            'show_answers' => (bool) $this->show_answers,
            'pass_percent' => $this->pass_percent,
            'time_limits' => $this->time_limits,
            'shuffle_questions' => (bool) $this->shuffle_questions,
            'shuffle_options' => (bool) $this->shuffle_options,
            'is_published' => (bool) $this->is_published,
            'generated_by' => $this->generated_by,
            'ai_model' => $this->ai_model,
            'ai_generated_at' => $this->datetime($this->ai_generated_at),
            'course_unit_id' => $this->course_unit_id,
            'unit' => new CourseUnitResource($this->whenLoaded('unit')),
            'subject' => new SubjectResource($this->whenLoaded('subject')),
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'sections' => TestSectionResource::collection($this->whenLoaded('sections')),
            'questions' => TestQuestionResource::collection($this->whenLoaded('questions')),
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
