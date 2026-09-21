<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PaymentResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'paid_at' => $this->datetime($this->paid_at),
            'receipt_url' => $this->fileUrl($this->receipt_path),
            'receipt_path' => $this->receipt_path,
            'note' => $this->note,
            'invoice_id' => $this->invoice_id,
            'student' => new StudentResource($this->whenLoaded('student')),
            'accepted_by' => new UserResource($this->whenLoaded('acceptedBy')),
        ];
    }
}
