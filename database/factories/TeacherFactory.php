<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Teacher> */
class TeacherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => config('school.id'),
            'user_id' => User::factory()->withRole('teacher'),
            'employee_no' => 'OQ-'.fake()->unique()->numberBetween(100, 9999),
            'specialization' => 'Italyan tili',
            'gender' => fake()->randomElement(['male', 'female']),
            'hired_at' => now()->subYears(fake()->numberBetween(1, 15))->toDateString(),
            'salary_type' => Teacher::SALARY_FIXED,
            'fixed_salary' => 5_000_000,
            'per_lesson_rate' => 0,
            'is_active' => true,
        ];
    }

    /** Soatbay (dars soniga qarab) to'lanadigan o'qituvchi */
    public function perLesson(float $rate = 80000): static
    {
        return $this->state(fn () => [
            'salary_type' => Teacher::SALARY_PER_LESSON,
            'fixed_salary' => 0,
            'per_lesson_rate' => $rate,
        ]);
    }
}
