<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AttendanceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'class_id' => $this->class_id,
            'date' => $this->date($this->date),
            'status' => $this->status,
            // manual — qo'lda belgilangan, device — Face ID/turniket (kelajakda)
            'source' => $this->source,
            'device_id' => $this->device_id,
            'marked_at' => $this->datetime($this->marked_at),
            'note' => $this->note,
            'student' => new StudentResource($this->whenLoaded('student')),
            'marked_by' => new UserResource($this->whenLoaded('markedBy')),
        ];
    }
}
