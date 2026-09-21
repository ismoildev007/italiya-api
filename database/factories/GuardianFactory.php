<?php

namespace Database\Factories;

use App\Models\Guardian;
use Database\Seeders\Data\UzbekNames;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Guardian> */
class GuardianFactory extends Factory
{
    public function definition(): array
    {
        $seed = fake()->unique()->numberBetween(1, 999999);

        return [
            'school_id' => config('school.id'),
            'firstname' => fake()->randomElement(UzbekNames::MALE_FIRST),
            'lastname' => UzbekNames::surname(fake()->randomElement(UzbekNames::SURNAME_STEMS), false),
            'phone' => UzbekNames::phone($seed),
            'link_code' => Str::upper(Str::random(8)),
        ];
    }

    /** Telegram botga bog'langan ota-ona */
    public function linked(): static
    {
        return $this->state(fn () => [
            'telegram_chat_id' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'link_code' => null,
            'linked_at' => now(),
        ]);
    }
}
