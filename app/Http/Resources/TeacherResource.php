<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TeacherResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_no' => $this->employee_no,
            'full_name' => $this->user?->full_name,
            'specialization' => $this->specialization,
            'birth_date' => $this->date($this->birth_date),
            'gender' => $this->gender,
            'phone' => $this->user?->phone,
            'address' => $this->address,
            'hired_at' => $this->date($this->hired_at),
            // Ish haqi rejimi har bir o'qituvchi uchun alohida
            'salary_type' => $this->salary_type,
            'fixed_salary' => (float) $this->fixed_salary,
            'per_lesson_rate' => (float) $this->per_lesson_rate,
            'is_active' => (bool) $this->is_active,
            'user' => new UserResource($this->whenLoaded('user')),
            'supervised_classes' => SchoolClassResource::collection($this->whenLoaded('supervisedClasses')),
            'assignments' => ClassSubjectTeacherResource::collection($this->whenLoaded('assignments')),
        ];
    }
}
