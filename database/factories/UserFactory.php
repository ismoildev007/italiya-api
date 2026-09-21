<?php

namespace Database\Factories;

use App\Models\User;
use Database\Seeders\Data\UzbekNames;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        $female = fake()->boolean();
        $stem = fake()->randomElement(UzbekNames::SURNAME_STEMS);

        return [
            'school_id' => config('school.id'),
            'firstname' => fake()->randomElement($female ? UzbekNames::FEMALE_FIRST : UzbekNames::MALE_FIRST),
            'lastname' => UzbekNames::surname($stem, $female),
            'middlename' => UzbekNames::patronymic(fake()->randomElement(UzbekNames::MALE_FIRST), $female),
            'username' => Str::lower(fake()->unique()->userName()),
            'phone' => UzbekNames::phone(fake()->unique()->numberBetween(1, 999999)),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('parol123'),
            'status' => 'active',
            'locale' => 'uz',
            'remember_token' => Str::random(10),
        ];
    }

    public function withRole(string $role): static
    {
        return $this->afterCreating(fn ($user) => $user->roles()->syncWithoutDetaching([$role]));
    }

    public function blocked(): static
    {
        return $this->state(fn () => ['status' => 'blocked']);
    }
}
