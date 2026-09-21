<?php

use App\Models\Permission;
use App\Models\Role;

beforeEach(function () {
    seedRbac();
});

it('permissionlarni route\'lardan avtomatik generatsiya qiladi', function () {
    expect(Permission::count())->toBeGreaterThan(100)
        ->and(Permission::find('student-index'))->not->toBeNull()
        ->and(Permission::find('payment-store'))->not->toBeNull();
});

it('permissionlarni kontroller bo\'yicha guruhlaydi', function () {
    expect(Permission::find('student-index')->group)->toBe('student')
        ->and(Permission::find('school_class-index')->group)->toBe('school_class');
});

it('superadminga hamma joyga ruxsat beradi (permission biriktirilmagan bo\'lsa ham)', function () {
    $user = actingAsUser('superadmin');

    expect($user->roles()->first()->permissions()->count())->toBe(0);

    $this->getJson('/api/v1/users')->assertOk();
    $this->getJson('/api/v1/payments')->assertOk();
    $this->getJson('/api/v1/reports/dashboard')->assertOk();
});

it('o\'qituvchiga moliya bo\'limini ochmaydi', function () {
    actingAsUser('teacher');

    $this->getJson('/api/v1/students')->assertOk();
    $this->getJson('/api/v1/payments')->assertStatus(403)->assertJsonPath('required_permission', 'payment-index');
    $this->getJson('/api/v1/invoices')->assertStatus(403);
});

it('buxgalterga moliyani ochadi, lekin baho qo\'yishni taqiqlaydi', function () {
    actingAsUser('accountant');

    $this->getJson('/api/v1/payments')->assertOk();
    $this->getJson('/api/v1/invoices')->assertOk();
    $this->postJson('/api/v1/grades', [])->assertStatus(403);
});

it('rolga permissionlarni almashtiradi', function () {
    actingAsUser('superadmin');

    $this->putJson('/api/v1/roles/teacher/permissions', [
        'permissions' => ['student-index', 'grade-index'],
    ])->assertOk()->assertJsonPath('data.assigned', 2);

    expect(Role::find('teacher')->permissions()->pluck('name')->sort()->values()->all())
        ->toBe(['grade-index', 'student-index']);
});
