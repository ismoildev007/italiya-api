<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class MeetingResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'platform' => $this->platform,
            'link' => $this->link,
            'starts_at' => $this->datetime($this->starts_at),
            'duration_minutes' => $this->duration_minutes,
            'levels' => $this->levels,
            'tracks' => $this->tracks,
            'status' => $this->status,
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
            'groups' => SchoolClassResource::collection($this->whenLoaded('groups')),
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
