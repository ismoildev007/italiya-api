<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CourseMaterialResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_unit_id' => $this->course_unit_id,
            'class_id' => $this->class_id,
            'level' => $this->level,
            'track' => $this->track,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->file_url,
            'youtube_id' => $this->youtubeId(),
            'mime' => $this->mime,
            'size' => $this->size,
            'max_plays' => $this->max_plays,
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
