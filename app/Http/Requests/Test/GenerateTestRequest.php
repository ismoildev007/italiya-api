<?php

namespace App\Http\Requests\Test;

use App\Http\Requests\BaseRequest;
use App\Models\TestTemplate;
use App\Support\Feature;
use App\Support\Level;
use Illuminate\Validation\Rule;

/** AI test generatsiyasi: daraja, ko'nikmalar, mavzu, har bo'limdagi savollar soni, tur. */
class GenerateTestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'skills' => 'nullable|array|min:1',
            'skills.*' => Rule::in(Feature::skills()),
            'topic' => 'nullable|string|max:255',
            'question_count' => 'required|integer|min:1|max:30',
            'kind' => ['nullable', Rule::in(TestTemplate::KINDS)],
            'difficulty' => 'nullable|in:easy,medium,hard,adaptive',
            'student_id' => 'nullable|exists:students,id',
            'class_id' => 'nullable|exists:school_classes,id',
            'translation_language' => ['nullable', Rule::in(array_keys(config('academy.translation_languages')))],
            'instructions' => 'nullable|string|max:1000',
        ];
    }
}
