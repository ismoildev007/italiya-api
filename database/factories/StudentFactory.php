<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Student;
use Database\Seeders\Data\UzbekNames;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Student> */
class StudentFactory extends Factory
{
    public function definition(): array
    {
        $female = fake()->boolean();
        $stem = fake()->randomElement(UzbekNames::SURNAME_STEMS);
        $seed = fake()->unique()->numberBetween(1, 999999);

        return [
            'school_id' => config('school.id'),
            'class_id' => SchoolClass::query()->value('id') ?? SchoolClass::factory(),
            'student_no' => now()->year.'-'.str_pad((string) $seed, 4, '0', STR_PAD_LEFT),
            'firstname' => fake()->randomElement($female ? UzbekNames::FEMALE_FIRST : UzbekNames::MALE_FIRST),
            'lastname' => UzbekNames::surname($stem, $female),
            'middlename' => UzbekNames::patronymic(fake()->randomElement(UzbekNames::MALE_FIRST), $female),
            'birth_date' => now()->subYears(fake()->numberBetween(7, 17))->toDateString(),
            'gender' => $female ? 'female' : 'male',
            'phone' => UzbekNames::phone($seed),
            'address' => UzbekNames::address($seed),
            'enrolled_at' => now()->startOfYear()->toDateString(),
            'status' => 'active',
        ];
    }
}
