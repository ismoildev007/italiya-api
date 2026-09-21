<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Support\Level;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SchoolClass> */
class SchoolClassFactory extends Factory
{
    public function definition(): array
    {
        $level = fake()->randomElement(Level::all());
        $letter = fake()->randomElement(['A', 'B', 'V', 'G']);

        return [
            'school_id' => config('school.id'),
            'academic_year_id' => AcademicYear::active()->value('id') ?? AcademicYear::factory()->active(),
            'name' => "{$level}-{$letter}".fake()->unique()->numberBetween(1, 9999),
            'level' => $level,
            'track' => 'general',
            'grade' => Level::ordinal($level),
            'letter' => $letter,
            'shift' => fake()->randomElement([1, 2]),
            'capacity' => 30,
            'is_active' => true,
        ];
    }
}
