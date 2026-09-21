<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TestTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TestTemplate> */
class TestTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => config('school.id'),
            'subject_id' => Subject::query()->value('id') ?? Subject::factory(),
            'teacher_id' => Teacher::query()->value('id') ?? Teacher::factory(),
            'grade' => fake()->numberBetween(5, 9),
            'title' => 'Test — '.fake()->unique()->numberBetween(1, 99999),
            'topic' => 'Sinov mavzusi',
            'difficulty' => 'medium',
            'generated_by' => TestTemplate::SOURCE_MANUAL,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'duration_minutes' => 30,
            'is_published' => true,
        ];
    }
}
