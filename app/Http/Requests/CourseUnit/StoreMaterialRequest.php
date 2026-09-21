<?php

namespace App\Http\Requests\CourseUnit;

use App\Http\Requests\BaseRequest;
use App\Models\CourseMaterial;
use App\Support\Level;
use Illuminate\Validation\Rule;

/** Guruh kutubxonasi: video darslik, kitob (PDF), fayl yoki havola. */
class StoreMaterialRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'course_unit_id' => 'nullable|exists:course_units,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'level' => ['nullable', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'type' => ['nullable', Rule::in(CourseMaterial::TYPES)],
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'url' => 'nullable|url|max:255',
            'file' => 'required_without:url|nullable|file|max:512000',
            'max_plays' => 'nullable|integer|min:1|max:20',
        ];
    }
}
