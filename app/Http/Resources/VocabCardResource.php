<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class VocabCardResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => $this->order,
            'word' => $this->word,
            'translations' => $this->translations,
            'category' => $this->category,
            'part_of_speech' => $this->part_of_speech,
            'example' => $this->example,
            'example_translations' => $this->example_translations,
            'pronunciation' => $this->pronunciation,
            'audio_url' => $this->audio_url,
            'image_url' => $this->fileUrl($this->image_path),
            'ai_generated' => (bool) $this->ai_generated,
        ];
    }
}
