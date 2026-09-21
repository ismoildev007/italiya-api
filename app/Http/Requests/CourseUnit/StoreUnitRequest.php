<?php

namespace App\Http\Requests\CourseUnit;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

/** Darslik birligi (level × track × skill). */
class StoreUnitRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'subject_id' => 'nullable|exists:subjects,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'level' => ['required', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'order' => 'nullable|integer|min:1',
            'skill' => ['required', Rule::in(array_keys(config('academy.skills')))],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'media_script' => 'nullable|string',
            'media_url' => 'nullable|url|max:255',
            'media' => 'nullable|file|extensions:mp3,m4a,wav,ogg,oga,webm,mp4,mov,mkv,aac|max:204800',
            // necha marta tinglash/ko'rish mumkin (null = cheksiz)
            'max_plays' => 'nullable|integer|min:1|max:20',
            'time_limit_minutes' => 'nullable|integer|min:1|max:180',
            'test_template_id' => 'nullable|exists:test_templates,id',
            'is_published' => 'boolean',
            'generated_by' => 'nullable|in:ai,manual',
            'ai_model' => 'nullable|string|max:100',
            'generate_audio' => 'boolean',
        ];
    }
}
