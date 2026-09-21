<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TeacherSalaryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period' => $this->period_label,
            'period_year' => $this->period_year,
            'period_month' => $this->period_month,
            'salary_type' => $this->salary_type,
            'base_amount' => (float) $this->base_amount,
            'lessons_count' => $this->lessons_count,
            'per_lesson_rate' => (float) $this->per_lesson_rate,
            'bonus' => (float) $this->bonus,
            'deduction' => (float) $this->deduction,
            'total_amount' => (float) $this->total_amount,
            'status' => $this->status,
            'paid_at' => $this->datetime($this->paid_at),
            'note' => $this->note,
            'teacher' => new TeacherResource($this->whenLoaded('teacher')),
        ];
    }
}
