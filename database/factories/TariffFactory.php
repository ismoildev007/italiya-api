<?php

namespace Database\Factories;

use App\Models\Tariff;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tariff> */
class TariffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_id' => config('school.id'),
            'name' => 'Tarif '.fake()->unique()->numberBetween(1, 99999),
            'monthly_price' => fake()->randomElement([900000, 1500000, 1850000, 2400000]),
            'is_active' => true,
        ];
    }
}
