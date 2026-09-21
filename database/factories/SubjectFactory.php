<?php

namespace Database\Factories;

use App\Models\Subject;
use Database\Seeders\Data\UzbekNames;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subject> */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        [$name, $code] = fake()->randomElement(UzbekNames::SUBJECTS);

        return [
            'school_id' => config('school.id'),
            'name' => $name.' '.fake()->unique()->numberBetween(1, 9999),
            'code' => $code,
            'is_active' => true,
        ];
    }
}
