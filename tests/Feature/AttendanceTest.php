<?php

use App\Models\Attendance;
use App\Models\Guardian;
use App\Models\NotificationEvent;
use App\Models\SchoolClass;
use App\Models\Student;

beforeEach(function () {
    seedRbac();
    $this->class = SchoolClass::factory()->create();
    $this->students = Student::factory()->count(3)->create(['class_id' => $this->class->id]);
});

it('sinf jurnalini bo\'sh holatda qaytaradi', function () {
    actingAsUser('class_teacher');

    $this->getJson("/api/v1/classes/{$this->class->id}/attendance/journal?date=2026-09-08")
        ->assertOk()
        ->assertJsonPath('data.date', '2026-09-08')
        ->assertJsonCount(3, 'data.students')
        ->assertJsonPath('data.students.0.marked', false);
});

it('jurnalni to\'plamli saqlaydi va manba sifatida `manual` yozadi', function () {
    actingAsUser('class_teacher');

    $this->postJson("/api/v1/classes/{$this->class->id}/attendance/journal", [
        'date' => '2026-09-08',
        'records' => $this->students->map(fn ($s, $i) => [
            'student_id' => $s->id,
            'status' => ['present', 'absent', 'late'][$i],
        ])->all(),
    ])->assertOk()->assertJsonPath('data.saved', 3);

    expect(Attendance::count())->toBe(3)
        ->and(Attendance::pluck('source')->unique()->all())->toBe(['manual']);
});

it('bir kunda bir o\'quvchiga ikkinchi yozuv yaratmaydi, mavjudini yangilaydi', function () {
    actingAsUser('class_teacher');
    $student = $this->students->first();

    foreach (['absent', 'present'] as $status) {
        $this->postJson("/api/v1/classes/{$this->class->id}/attendance/journal", [
            'date' => '2026-09-08',
            'records' => [['student_id' => $student->id, 'status' => $status]],
        ])->assertOk();
    }

    expect(Attendance::where('student_id', $student->id)->count())->toBe(1)
        ->and(Attendance::where('student_id', $student->id)->value('status'))->toBe('present');
});

it('kelmagan o\'quvchi uchun ota-onaga xabar hodisasi yaratadi', function () {
    actingAsUser('class_teacher');
    $student = $this->students->first();

    $guardian = Guardian::factory()->linked()->create();
    $guardian->students()->attach($student->id, [
        'school_id' => config('school.id'), 'relation' => 'father', 'is_primary' => true,
    ]);

    $this->postJson("/api/v1/classes/{$this->class->id}/attendance/journal", [
        'date' => '2026-09-08',
        'records' => [['student_id' => $student->id, 'status' => 'absent']],
    ])->assertOk();

    expect(NotificationEvent::where('type', 'attendance_marked')->count())->toBe(1)
        ->and(NotificationEvent::first()->status)->toBe('pending');
});

it('davomat foizini to\'g\'ri hisoblaydi', function () {
    actingAsUser('class_teacher');
    $student = $this->students->first();

    foreach ([['2026-09-01', 'present'], ['2026-09-02', 'late'], ['2026-09-03', 'absent'], ['2026-09-04', 'present']] as [$date, $status]) {
        Attendance::create([
            'school_id' => config('school.id'), 'student_id' => $student->id,
            'class_id' => $this->class->id, 'date' => $date, 'status' => $status, 'source' => 'manual',
        ]);
    }

    $this->getJson("/api/v1/students/{$student->id}/attendance?from=2026-09-01&to=2026-09-30")
        ->assertOk()
        ->assertJsonPath('data.summary.total', 4)
        ->assertJsonPath('data.summary.present', 2)
        ->assertJsonPath('data.summary.absent', 1)
        ->assertJsonPath('data.summary.late', 1)
        // keldi + kech qoldi = 3 / 4
        ->assertJsonPath('data.summary.attendance_rate', 75);
});

it('noto\'g\'ri statusni rad etadi', function () {
    actingAsUser('class_teacher');

    $this->postJson("/api/v1/classes/{$this->class->id}/attendance/journal", [
        'date' => '2026-09-08',
        'records' => [['student_id' => $this->students->first()->id, 'status' => 'kelmadi']],
    ])->assertStatus(422);
});
