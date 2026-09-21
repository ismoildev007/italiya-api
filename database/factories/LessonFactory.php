<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Lesson;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lesson> */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => config('school.id'),
            'academic_year_id' => AcademicYear::active()->value('id') ?? AcademicYear::factory()->active(),
            'class_id' => SchoolClass::query()->value('id') ?? SchoolClass::factory(),
            'subject_id' => Subject::query()->value('id') ?? Subject::factory(),
            'teacher_id' => Teacher::query()->value('id') ?? Teacher::factory(),
            'date' => now()->toDateString(),
            'topic' => fake()->randomElement(['Yangi mavzu bayoni', 'Takrorlash', 'Amaliy mashg\'ulot']),
            'duration_hours' => 1,
            'status' => Lesson::STATUS_CONDUCTED,
        ];
    }
}
