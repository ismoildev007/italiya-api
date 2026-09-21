<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    seedRbac();
});

it('barcha standart rollarni inglizcha nom bilan yaratadi', function () {
    $expected = ['superadmin', 'admin', 'class_teacher', 'teacher', 'accountant', 'parent', 'student'];

    expect(Role::pluck('name')->sort()->values()->all())->toBe(collect($expected)->sort()->values()->all());
});

it('barcha standart rollarni tizim roli deb belgilaydi', function () {
    expect(Role::where('is_system', false)->count())->toBe(0)
        ->and(Role::count())->toBe(count(RoleSeeder::ROLES));
});

it('tizim rolini API orqali o\'chirishga yo\'l qo\'ymaydi', function () {
    actingAsUser('superadmin');

    foreach (['superadmin', 'admin', 'teacher', 'student', 'parent', 'accountant', 'class_teacher'] as $role) {
        $this->deleteJson("/api/v1/roles/{$role}")->assertStatus(403);
    }

    expect(Role::count())->toBe(count(RoleSeeder::ROLES));
});

it('tizim rolini model darajasida ham o\'chirib bo\'lmaydi', function () {
    expect(fn () => Role::find('teacher')->delete())
        ->toThrow(HttpException::class);

    expect(Role::find('teacher'))->not->toBeNull();
});

it('tizim rolining texnik nomini o\'zgartirmaydi, lekin sarlavhasini tahrirlaydi', function () {
    actingAsUser('superadmin');

    $this->putJson('/api/v1/roles/teacher', ['name' => 'oqituvchi', 'title' => 'Fan o\'qituvchisi'])
        ->assertOk();

    expect(Role::find('teacher'))->not->toBeNull()
        ->and(Role::find('teacher')->title)->toBe('Fan o\'qituvchisi')
        ->and(Role::find('oqituvchi'))->toBeNull();
});

it('superadmin rolini o\'zgartirishga yo\'l qo\'ymaydi', function () {
    actingAsUser('superadmin');

    $this->putJson('/api/v1/roles/superadmin', ['title' => 'Boshqa'])->assertStatus(403);
    $this->putJson('/api/v1/roles/superadmin/permissions', ['permissions' => []])->assertStatus(403);
});

it('yangi rol yaratadi va uni o\'chirishga ruxsat beradi', function () {
    actingAsUser('superadmin');

    $this->postJson('/api/v1/roles', [
        'name' => 'head_teacher',
        'title' => 'Zavuch',
        'permissions' => ['student-index'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_system', false)
        ->assertJsonPath('data.is_deletable', true)
        ->assertJsonPath('data.permissions_count', 1);

    $this->deleteJson('/api/v1/roles/head_teacher')->assertOk();

    expect(Role::find('head_teacher'))->toBeNull();
});

it('rol nomini faqat lotin kichik harflarida qabul qiladi', function () {
    actingAsUser('superadmin');

    $this->postJson('/api/v1/roles', ['name' => 'Bosh Ustoz', 'title' => 'X'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('rolga biriktirilgan foydalanuvchilar bo\'lsa uni o\'chirmaydi', function () {
    actingAsUser('superadmin');

    $this->postJson('/api/v1/roles', ['name' => 'temp_role', 'title' => 'Vaqtinchalik'])->assertCreated();

    User::factory()->create()->roles()->sync(['temp_role']);

    $this->deleteJson('/api/v1/roles/temp_role')->assertStatus(422);
});
