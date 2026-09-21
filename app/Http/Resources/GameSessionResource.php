<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class GameSessionResource extends BaseResource
{
    /** O'yin davomida to'g'ri javoblar yashiriladi */
    public bool $hideAnswers = false;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'started_at' => $this->datetime($this->started_at),
            'finished_at' => $this->datetime($this->finished_at),
            'total_rounds' => $this->total_rounds,
            'correct' => $this->correct,
            'wrong' => $this->wrong,
            'score' => $this->score,
            'duration_seconds' => $this->duration_seconds,
            'source' => $this->source,
            'rounds' => collect($this->rounds ?? [])->map(fn ($r) => $this->hideAnswers ? collect($r)->except(['answer'])->all() : $r)->values(),
            'details' => $this->details,
            'game' => new GameResource($this->whenLoaded('game')),
            'student' => new StudentResource($this->whenLoaded('student')),
        ];
    }
}
