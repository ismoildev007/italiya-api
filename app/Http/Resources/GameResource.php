<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class GameResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'type_label' => config("academy.games.types.{$this->type}"),
            'level' => $this->level,
            'track' => $this->track,
            'card_count' => $this->card_count,
            'seconds_per_card' => $this->seconds_per_card,
            'round_types' => $this->roundTypes(),
            'prompt_language' => $this->prompt_language,
            'answer_language' => $this->answer_language,
            'points_per_correct' => $this->points_per_correct,
            'is_active' => (bool) $this->is_active,
            'deck' => new VocabDeckResource($this->whenLoaded('deck')),
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'sessions_count' => $this->whenCounted('sessions'),
            'my_sessions_count' => $this->when($this->getAttribute('my_sessions_count') !== null, $this->getAttribute('my_sessions_count')),
            'my_best_score' => $this->when($this->getAttribute('my_best_score') !== null, $this->getAttribute('my_best_score')),
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
