<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AcademicYear> */
class AcademicYearFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfYear()->addMonths(8);

        return [
            'school_id' => config('school.id'),
            'name' => $start->year.'-'.($start->year + 1),
            'starts_on' => $start->toDateString(),
            'ends_on' => $start->copy()->addMonths(9)->toDateString(),
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }
}
