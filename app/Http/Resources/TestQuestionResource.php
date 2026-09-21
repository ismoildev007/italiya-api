<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TestQuestionResource extends BaseResource
{
    /** O'quvchiga to'g'ri javob ko'rsatilmaydi */
    public bool $hideAnswers = false;

    public function toArray(Request $request): array
    {
        return array_filter([
            'id' => $this->id,
            'section_id' => $this->test_section_id,
            'order' => $this->order,
            'body' => $this->body,
            'type' => $this->type,
            'skill' => $this->skill,
            // written — yozma javob, voice — ovozli javob (OpenAI STT + Gemini tahlil)
            'answer_mode' => $this->answer_mode,
            'score' => (float) $this->score,
            'media_script' => $this->hideAnswers ? null : $this->media_script,
            'media_url' => $this->fileUrl($this->media_path),
            'max_plays' => $this->max_plays,
            'similarity_threshold' => $this->similarity_threshold,
            'translations' => $this->translations,
            'meta' => $this->meta,
            'ai_generated' => (bool) $this->ai_generated,
            'correct_answer' => $this->hideAnswers ? null : $this->correct_answer,
            'model_answer' => $this->hideAnswers ? null : $this->model_answer,
            'explanation' => $this->hideAnswers ? null : $this->explanation,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($option) => array_filter([
                'id' => $option->id,
                'order' => $option->order,
                'body' => $option->body,
                'is_correct' => $this->hideAnswers ? null : (bool) $option->is_correct,
            ], fn ($v) => $v !== null))),
        ], fn ($v) => $v !== null);
    }
}
