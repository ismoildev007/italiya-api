<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class StudentDiscountResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_id' => $this->student_id,
            'type' => $this->type,
            'value' => (float) $this->value,
            'reason' => $this->reason,
            'starts_on' => $this->date($this->starts_on),
            'ends_on' => $this->date($this->ends_on),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
