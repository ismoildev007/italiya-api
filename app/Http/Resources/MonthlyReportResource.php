<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class MonthlyReportResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'year' => $this->year,
            'month' => $this->month,
            'data' => $this->data,
            'summary' => $this->summary,
            'sent_at' => $this->datetime($this->sent_at),
            'student' => new StudentResource($this->whenLoaded('student')),
        ];
    }
}
