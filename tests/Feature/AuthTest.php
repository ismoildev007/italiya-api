<?php

use App\Models\User;

beforeEach(function () {
    seedRbac();
});

it('foydalanuvchini login va parol orqali tizimga kiritadi', function () {
    User::factory()->create(['username' => 'direktor'])->roles()->sync(['admin']);

    $this->postJson('/api/v1/login', [
        'login' => 'direktor',
        'password' => 'parol123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['access_token', 'token_type', 'user', 'permissions']]);
});

it('telefon raqami orqali ham kirishga ruxsat beradi', function () {
    $user = User::factory()->create(['phone' => '+998901112233']);
    $user->roles()->sync(['admin']);

    $this->postJson('/api/v1/login', [
        'login' => '+998901112233',
        'password' => 'parol123',
    ])->assertOk();
});

it('noto\'g\'ri parolni rad etadi', function () {
    User::factory()->create(['username' => 'test.user']);

    $this->postJson('/api/v1/login', ['login' => 'test.user', 'password' => 'xato'])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

it('bloklangan akkauntni kiritmaydi', function () {
    User::factory()->blocked()->create(['username' => 'blocked.user']);

    $this->postJson('/api/v1/login', ['login' => 'blocked.user', 'password' => 'parol123'])
        ->assertStatus(422)
        ->assertJsonPath('errors.login.0', 'Akkaunt bloklangan. Administratorga murojaat qiling.');
});

it('tokensiz so\'rovni 401 bilan rad etadi', function () {
    $this->getJson('/api/v1/users')->assertStatus(401);
});

it('me endpointi foydalanuvchi, ruxsatlar va maktab ma\'lumotini qaytaradi', function () {
    actingAsUser('admin');

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonStructure(['data' => ['user', 'permissions', 'school' => ['name', 'currency']]]);
});

it('parolni almashtiradi', function () {
    $user = actingAsUser('admin');

    $this->postJson('/api/v1/change-password', [
        'current_password' => 'parol123',
        'password' => 'yangiparol',
        'password_confirmation' => 'yangiparol',
    ])->assertOk();

    expect(Hash::check('yangiparol', $user->fresh()->password))->toBeTrue();
});
