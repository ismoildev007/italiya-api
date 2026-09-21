<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class InvoiceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'period' => $this->period_label,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'base_amount' => (float) $this->base_amount,
            'discount_amount' => (float) $this->discount_amount,
            'total_amount' => (float) $this->total_amount,
            'paid_amount' => (float) $this->paid_amount,
            'remaining' => $this->remaining,
            'status' => $this->status,
            'due_date' => $this->date($this->due_date),
            'generated_at' => $this->datetime($this->generated_at),
            'note' => $this->note,
            'student' => new StudentResource($this->whenLoaded('student')),
            'tariff' => new TariffResource($this->whenLoaded('tariff')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
