<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class NotificationEventResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'channel' => $this->channel,
            'recipient_type' => $this->recipient_type,
            'recipient_id' => $this->recipient_id,
            'recipient_address' => $this->recipient_address,
            'student_id' => $this->student_id,
            'title' => $this->title,
            'message' => $this->message,
            'payload' => $this->payload,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'sent_at' => $this->datetime($this->sent_at),
            'error' => $this->error,
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
