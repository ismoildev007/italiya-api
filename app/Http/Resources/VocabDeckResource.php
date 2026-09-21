<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class VocabDeckResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'level' => $this->level,
            'track' => $this->track,
            'category' => $this->category,
            'language' => $this->language,
            'description' => $this->description,
            'cards_count' => $this->cards_count,
            'is_published' => (bool) $this->is_published,
            'generated_by' => $this->generated_by,
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'cards' => VocabCardResource::collection($this->whenLoaded('cards')),
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
