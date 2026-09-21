<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

require_once __DIR__.'/AcademyHelpers.php';

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Tizim rollari va route'lardan generatsiya qilingan ruxsatlarni tayyorlaydi.
 */
function seedRbac(): void
{
    test()->seed(RoleSeeder::class);
    test()->seed(PermissionSeeder::class);
}

/**
 * Berilgan rol(lar) bilan foydalanuvchi yaratib, uni autentifikatsiya qiladi.
 *
 * @param  string|array<int, string>  $roles
 */
function actingAsUser(string|array $roles = ['admin']): User
{
    $user = User::factory()->create();
    $user->roles()->sync((array) $roles);

    test()->actingAs($user, 'sanctum');

    return $user;
}
