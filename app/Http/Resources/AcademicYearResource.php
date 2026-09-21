<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AcademicYearResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_on' => $this->date($this->starts_on),
            'ends_on' => $this->date($this->ends_on),
            'is_active' => (bool) $this->is_active,
            'classes_count' => $this->whenCounted('classes'),
        ];
    }
}
