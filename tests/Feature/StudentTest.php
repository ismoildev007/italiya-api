<?php

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;

beforeEach(function () {
    seedRbac();
    actingAsUser('admin');
    $this->class = SchoolClass::factory()->create();
});

it('o\'quvchi qo\'shadi va sinf tarixini yozadi', function () {
    $this->postJson('/api/v1/students', [
        'firstname' => 'Sardor',
        'lastname' => 'Yusupov',
        'class_id' => $this->class->id,
        'gender' => 'male',
        'birth_date' => '2015-04-12',
    ])->assertCreated()->assertJsonPath('data.full_name', 'Yusupov Sardor');

    expect(StudentClassHistory::count())->toBe(1)
        ->and(StudentClassHistory::first()->reason)->toBe('enrolled');
});

it('o\'quvchiga akkaunt yaratganda `student` rolini beradi', function () {
    $this->postJson('/api/v1/students', [
        'firstname' => 'Aziza', 'lastname' => 'Karimova', 'class_id' => $this->class->id,
        'create_account' => true, 'password' => 'parol123',
    ])->assertCreated();

    $student = Student::latest('id')->first();

    expect($student->user)->not->toBeNull()
        ->and($student->user->roleNames())->toBe(['student']);
});

it('sinfni o\'zgartirganda eski yozuvni arxivlaydi, o\'chirmaydi', function () {
    $student = Student::factory()->create(['class_id' => $this->class->id]);
    StudentClassHistory::create([
        'school_id' => config('school.id'), 'student_id' => $student->id,
        'class_id' => $this->class->id, 'academic_year_id' => $this->class->academic_year_id,
        'from_date' => now()->toDateString(), 'reason' => 'enrolled',
    ]);

    $yangi = SchoolClass::factory()->create();

    $this->postJson("/api/v1/students/{$student->id}/transfer", [
        'class_id' => $yangi->id,
        'reason' => 'promoted',
    ])->assertOk();

    expect($student->fresh()->class_id)->toBe($yangi->id)
        ->and(StudentClassHistory::where('student_id', $student->id)->count())->toBe(2)
        ->and(StudentClassHistory::where('class_id', $this->class->id)->first()->to_date)->not->toBeNull();
});

it('o\'quvchilarni sinf bo\'yicha filtrlaydi', function () {
    $boshqa = SchoolClass::factory()->create();
    Student::factory()->count(3)->create(['class_id' => $this->class->id]);
    Student::factory()->count(2)->create(['class_id' => $boshqa->id]);

    $this->getJson("/api/v1/students?class_id={$this->class->id}")
        ->assertOk()
        ->assertJsonPath('meta.total', 3);
});

it('face_id va card_id maydonlarini saqlaydi (kelajakdagi Face ID uchun)', function () {
    $student = Student::factory()->create(['class_id' => $this->class->id]);

    $this->putJson("/api/v1/students/{$student->id}", [
        'face_id' => 'FACE-0001',
        'card_id' => 'CARD-0001',
    ])->assertOk();

    expect($student->fresh()->face_id)->toBe('FACE-0001')
        ->and($student->fresh()->card_id)->toBe('CARD-0001');
});
