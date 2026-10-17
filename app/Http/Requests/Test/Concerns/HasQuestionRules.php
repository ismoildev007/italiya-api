<?php

namespace App\Http\Requests\Test\Concerns;

use App\Models\TestTemplate;
use App\Support\Feature;
use App\Support\Level;
use Illuminate\Validation\Rule;

/**
 * Test shabloni, bo'limlar va savollar qoidalari — store/update so'rovlarida bir xil.
 * `sections[]` (IELTS uslubi) yoki eski `questions[]` (bo'limsiz) qabul qilinadi.
 */
trait HasQuestionRules
{
    protected function templateRules(string $presence): array
    {
        return [
            'subject_id' => 'nullable|exists:subjects,id',
            'teacher_id' => 'nullable|exists:teachers,id',
            'title' => "{$presence}|string|max:255",
            'topic' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'grade' => 'nullable|integer|min:1|max:11',
            'level' => [$presence === 'required' ? 'required' : 'sometimes', Rule::in(Level::all())],
            'track' => ['nullable', Rule::in(array_keys(config('academy.tracks')))],
            'kind' => ['nullable', Rule::in(TestTemplate::KINDS)],
            'skill' => ['nullable', Rule::in(Feature::skills())],
            'language' => 'nullable|string|size:2',
            'difficulty' => 'nullable|in:easy,medium,hard,adaptive',
            'generated_by' => 'nullable|in:ai,manual',
            'ai_model' => 'nullable|string|max:100',
            'ai_prompt' => 'nullable|string',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'duration_minutes' => 'nullable|integer|min:1|max:300',
            // null = cheksiz; mock uchun odatda 1
            'max_attempts' => 'nullable|integer|min:1|max:20',
            // listening audio necha marta eshitiladi; null = cheksiz
            'max_plays' => 'nullable|integer|min:1|max:10',
            'auto_grade_ai' => 'boolean',
            'show_answers' => 'boolean',
            'pass_percent' => 'nullable|integer|min:1|max:100',
            'time_limits' => 'nullable|array',
            'time_limits.*' => 'integer|min:1|max:180',
            'course_unit_id' => 'nullable|exists:course_units,id',
            'is_published' => 'boolean',
            'generate_audio' => 'boolean',
        ];
    }

    protected function sectionRules(): array
    {
        return [
            'sections' => 'sometimes|array|min:1',
            'sections.*.id' => 'nullable|integer',
            'sections.*.order' => 'nullable|integer|min:1',
            'sections.*.skill' => ['required', Rule::in(Feature::skills())],
            'sections.*.title' => 'nullable|string|max:255',
            'sections.*.instructions' => 'nullable|string',
            'sections.*.passage' => 'nullable|string',
            'sections.*.media_script' => 'nullable|string',
            'sections.*.media_path' => 'nullable|string|max:255',
            'sections.*.media_url' => 'nullable|url|max:255',
            'sections.*.max_plays' => 'nullable|integer|min:1|max:10',
            'sections.*.time_limit_minutes' => 'nullable|integer|min:1|max:180',
            'sections.*.ai_generated' => 'boolean',
            'sections.*.questions' => 'required|array|min:1',
        ] + $this->questionRules('sections.*.questions');
    }

    /**
     * AI generatsiyasidan keyin o'qituvchi tahrirlagan yakuniy savollar ro'yxati.
     */
    protected function questionRules(string $prefix = 'questions'): array
    {
        $rules = [
            "{$prefix}.*.id" => 'nullable|integer',
            "{$prefix}.*.order" => 'nullable|integer|min:1',
            "{$prefix}.*.body" => 'required|string',
            "{$prefix}.*.type" => ['required', Rule::in(Feature::questionTypes())],
            "{$prefix}.*.skill" => ['nullable', Rule::in(Feature::skills())],
            "{$prefix}.*.answer_mode" => 'nullable|in:written,voice',
            "{$prefix}.*.score" => 'nullable|numeric|min:0|max:100',
            "{$prefix}.*.correct_answer" => 'nullable|string',
            "{$prefix}.*.model_answer" => 'nullable|string',
            "{$prefix}.*.explanation" => 'nullable|string',
            "{$prefix}.*.media_script" => 'nullable|string',
            "{$prefix}.*.media_path" => 'nullable|string|max:255',
            "{$prefix}.*.max_plays" => 'nullable|integer|min:1|max:10',
            "{$prefix}.*.similarity_threshold" => 'nullable|numeric|min:0.3|max:1',
            "{$prefix}.*.translations" => 'nullable|array',
            "{$prefix}.*.meta" => 'nullable|array',
            "{$prefix}.*.ai_generated" => 'boolean',
            "{$prefix}.*.options" => 'nullable|array',
            "{$prefix}.*.options.*.body" => 'required|string',
            "{$prefix}.*.options.*.is_correct" => 'boolean',
        ];

        if ($prefix === 'questions') {
            $rules['questions'] = 'sometimes|array|min:1';
        }

        return $rules;
    }
}
