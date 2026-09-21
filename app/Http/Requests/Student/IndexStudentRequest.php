<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class IndexStudentRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'search' => 'nullable|string|max:100',
            'class_id' => 'nullable|exists:school_classes,id',
            'grade' => 'nullable|integer|min:1|max:11',
            'level' => ['nullable', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'status' => 'nullable|in:active,graduated,transferred,expelled',
            'tariff_id' => 'nullable|exists:tariffs,id',
            'debtors' => 'nullable|boolean',
        ];
    }
}
