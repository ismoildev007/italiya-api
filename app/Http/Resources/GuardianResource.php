<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class GuardianResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'middlename' => $this->middlename,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'workplace' => $this->workplace,
            'relation' => $this->whenPivotLoaded('guardian_student', fn () => $this->pivot->relation),
            'is_primary' => $this->whenPivotLoaded('guardian_student', fn () => (bool) $this->pivot->is_primary),
            // Telegram bot holati
            'telegram_linked' => $this->isLinked(),
            'link_code' => $this->link_code,
            'linked_at' => $this->datetime($this->linked_at),
            'students' => StudentResource::collection($this->whenLoaded('students')),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
