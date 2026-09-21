<?php

use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    seedRbac();
    $this->student = Student::factory()->create();
    $this->boshqa = Student::factory()->create();
});

/** O'quvchi akkaunti bilan kirish */
function actingAsStudent(Student $student): User
{
    $user = User::factory()->create();
    $user->roles()->sync(['student']);
    $student->update(['user_id' => $user->id]);

    test()->actingAs($user, 'sanctum');

    return $user;
}

it('o\'quvchi faqat o\'zini ko\'radi', function () {
    actingAsStudent($this->student);

    $this->getJson('/api/v1/portal/children')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->student->id);
});

it('o\'quvchi o\'z profili, davomati va baholarini ko\'ra oladi', function () {
    actingAsStudent($this->student);

    $this->getJson("/api/v1/portal/students/{$this->student->id}")->assertOk();
    $this->getJson("/api/v1/portal/students/{$this->student->id}/attendance")->assertOk();
    $this->getJson("/api/v1/portal/students/{$this->student->id}/grades")->assertOk();
    $this->getJson("/api/v1/portal/students/{$this->student->id}/tests")->assertOk();
    $this->getJson("/api/v1/portal/students/{$this->student->id}/payments")->assertOk();
});

it('o\'quvchi boshqa o\'quvchi ma\'lumotini ko\'ra olmaydi', function () {
    actingAsStudent($this->student);

    $this->getJson("/api/v1/portal/students/{$this->boshqa->id}")->assertStatus(403);
    $this->getJson("/api/v1/portal/students/{$this->boshqa->id}/grades")->assertStatus(403);
    $this->getJson("/api/v1/portal/students/{$this->boshqa->id}/payments")->assertStatus(403);
});

it('o\'quvchi boshqaruv bo\'limlariga kira olmaydi (faqat o\'qish huquqi)', function () {
    actingAsStudent($this->student);

    $this->getJson('/api/v1/students')->assertStatus(403);
    $this->getJson('/api/v1/payments')->assertStatus(403);
    $this->postJson('/api/v1/grades', [])->assertStatus(403);
    $this->deleteJson("/api/v1/students/{$this->student->id}")->assertStatus(403);
});

it('ota-ona faqat o\'z farzandlarini ko\'radi', function () {
    $user = User::factory()->create();
    $user->roles()->sync(['parent']);

    $guardian = Guardian::factory()->create(['user_id' => $user->id]);
    $guardian->students()->attach($this->student->id, [
        'school_id' => config('school.id'), 'relation' => 'father', 'is_primary' => true,
    ]);

    $this->actingAs($user, 'sanctum');

    $this->getJson('/api/v1/portal/children')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->student->id);

    $this->getJson("/api/v1/portal/students/{$this->student->id}/grades")->assertOk();
    $this->getJson("/api/v1/portal/students/{$this->boshqa->id}/grades")->assertStatus(403);
});
