<?php

use App\Models\User;

beforeEach(function () {
    seedRbac();
});

it('foydalanuvchi yaratadi va unga rol biriktiradi', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/users', [
        'firstname' => 'Dilshod',
        'lastname' => 'Ismoilov',
        'middlename' => 'Anvarovich',
        'username' => 'd.ismoilov',
        'phone' => '+998901010101',
        'password' => 'parol123',
        'roles' => ['accountant'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.username', 'd.ismoilov')
        ->assertJsonPath('data.role_names.0', 'accountant');
});

it('nuqtali loginni qabul qiladi, bo\'shliqli loginni rad etadi', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/users', [
        'firstname' => 'A', 'lastname' => 'B', 'username' => 'noto gri', 'password' => 'parol123', 'roles' => ['teacher'],
    ])->assertStatus(422)->assertJsonValidationErrors('username');
});

it('takrorlanuvchi login va telefonni rad etadi', function () {
    actingAsUser('admin');
    User::factory()->create(['username' => 'mavjud.user', 'phone' => '+998901112233']);

    $this->postJson('/api/v1/users', [
        'firstname' => 'A', 'lastname' => 'B', 'username' => 'mavjud.user',
        'phone' => '+998901112233', 'password' => 'parol123', 'roles' => ['teacher'],
    ])->assertStatus(422)->assertJsonValidationErrors(['username', 'phone']);
});

it('superadmin bo\'lmagan foydalanuvchi superadmin rolini biriktira olmaydi', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/users', [
        'firstname' => 'A', 'lastname' => 'B', 'username' => 'xaker',
        'password' => 'parol123', 'roles' => ['superadmin'],
    ])->assertStatus(403);
});

it('superadmin akkauntini boshqalar o\'zgartira va o\'chira olmaydi', function () {
    $superadmin = User::factory()->create(['username' => 'superadmin']);
    $superadmin->roles()->sync(['superadmin']);

    actingAsUser('admin');

    $this->putJson("/api/v1/users/{$superadmin->id}", ['firstname' => 'Xakerlangan'])->assertStatus(403);
    $this->deleteJson("/api/v1/users/{$superadmin->id}")->assertStatus(403);

    expect($superadmin->fresh()->firstname)->not->toBe('Xakerlangan');
});

it('foydalanuvchini bloklaydi va blokdan chiqaradi', function () {
    actingAsUser('admin');
    $user = User::factory()->create();
    $user->roles()->sync(['teacher']);

    $this->patchJson("/api/v1/users/{$user->id}/status", ['status' => 'blocked'])->assertOk();
    expect($user->fresh()->status)->toBe('blocked');

    $this->patchJson("/api/v1/users/{$user->id}/status", ['status' => 'active'])->assertOk();
    expect($user->fresh()->status)->toBe('active');
});

it('o\'z akkauntini o\'chirishga yo\'l qo\'ymaydi', function () {
    $user = actingAsUser('admin');

    $this->deleteJson("/api/v1/users/{$user->id}")->assertStatus(422);
});

it('foydalanuvchi rollarini almashtiradi', function () {
    actingAsUser('admin');
    $user = User::factory()->create();
    $user->roles()->sync(['teacher']);

    $this->putJson("/api/v1/users/{$user->id}/roles", ['roles' => ['teacher', 'class_teacher']])
        ->assertOk();

    expect($user->fresh()->roleNames())->toContain('teacher', 'class_teacher');
});

it('administrator parolni tiklaydi', function () {
    actingAsUser('admin');
    $user = User::factory()->create();
    $user->roles()->sync(['teacher']);

    $this->patchJson("/api/v1/users/{$user->id}/password", ['password' => 'yangiparol'])->assertOk();

    expect(Hash::check('yangiparol', $user->fresh()->password))->toBeTrue();
});

it('foydalanuvchilarni rol va qidiruv bo\'yicha filtrlaydi', function () {
    actingAsUser('admin');
    User::factory()->create(['lastname' => 'Toshmatov'])->roles()->sync(['teacher']);
    User::factory()->create(['lastname' => 'Qodirova'])->roles()->sync(['accountant']);

    $this->getJson('/api/v1/users?role=accountant')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.lastname', 'Qodirova');

    $this->getJson('/api/v1/users?search=Toshmatov')
        ->assertOk()
        ->assertJsonPath('meta.total', 1);
});
