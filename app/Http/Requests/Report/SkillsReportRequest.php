<?php

namespace App\Http\Requests\Report;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class SkillsReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'level' => ['nullable', Rule::in(Level::all())],
            'class_id' => 'nullable|exists:school_classes,id',
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
        ];
    }
}
