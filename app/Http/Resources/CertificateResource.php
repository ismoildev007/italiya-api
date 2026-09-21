<?php

namespace App\Http\Resources;

use App\Support\Level;
use Illuminate\Http\Request;

class CertificateResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'serial' => $this->serial,
            'verify_code' => $this->verify_code,
            'level' => $this->level,
            'level_title' => Level::title($this->level),
            'track' => $this->track,
            'period' => $this->period_label,
            'title' => $this->title,
            'attendance_score' => $this->attendance_score,
            'homework_score' => $this->homework_score,
            'test_score' => $this->test_score,
            'overall_score' => $this->overall_score,
            'grade_label' => $this->grade_label,
            'skills' => $this->skills,
            'issued_at' => $this->datetime($this->issued_at),
            'html_url' => url("/api/v1/certificates/{$this->serial}/html"),
            'verify_url' => url("/api/v1/certificates/verify/{$this->verify_code}"),
            'student' => new StudentResource($this->whenLoaded('student')),
        ];
    }
}
