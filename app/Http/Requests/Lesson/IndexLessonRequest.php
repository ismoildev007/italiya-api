<?php

namespace App\Http\Requests\Lesson;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasPeriodFilters;

class IndexLessonRequest extends BaseRequest
{
    use HasPeriodFilters;

    public function rules(): array
    {
        return $this->paginationRules() + $this->periodRules() + [
            'class_id' => 'nullable|exists:school_classes,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'subject_id' => 'nullable|exists:subjects,id',
            'status' => 'nullable|in:planned,conducted,canceled',
        ];
    }
}
