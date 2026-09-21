<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * O'quvchining javobi to'liq ko'rinishda: matn yoki ovoz transkripti,
 * original audio, o'xshashlik foizi va AI tahlili (ball + fikr-mulohaza).
 */
class TestAnswerResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_id' => $this->test_question_id,
            'question' => new TestQuestionResource($this->whenLoaded('question')),
            'selected_option_ids' => $this->selected_option_ids,
            'answer_text' => $this->answer_text,
            'transcript' => $this->transcript,
            'transcription_provider' => $this->transcription_provider,
            'audio_url' => $this->fileUrl($this->audio_path),
            'audio_path' => $this->audio_path,
            'option_order' => $this->option_order,
            'is_correct' => $this->is_correct,
            'similarity' => $this->similarity,
            'plays' => $this->plays,
            'score' => (float) $this->score,
            'feedback' => $this->feedback,
            'ai_score' => $this->ai_score !== null ? (float) $this->ai_score : null,
            'ai_feedback' => $this->ai_feedback,
            'ai_provider' => $this->ai_provider,
            'ai_evaluated_at' => $this->datetime($this->ai_evaluated_at),
        ];
    }
}
