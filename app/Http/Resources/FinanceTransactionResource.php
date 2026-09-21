<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class FinanceTransactionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'balance_after' => (float) $this->balance_after,
            'description' => $this->description,
            'meta' => $this->meta,
            'reference_type' => $this->reference_type ? class_basename($this->reference_type) : null,
            'reference_id' => $this->reference_id,
            'author' => new UserResource($this->whenLoaded('author')),
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
