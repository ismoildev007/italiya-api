<?php

use App\Models\Lesson;
use App\Models\Teacher;
use App\Services\SalaryService;

beforeEach(function () {
    seedRbac();
    $this->salaries = app(SalaryService::class);
});

it('fiksed rejimda oylik maoshni qaytaradi', function () {
    $teacher = Teacher::factory()->create(['fixed_salary' => 6_000_000]);

    $calc = $this->salaries->calculate($teacher, now()->year, now()->month);

    expect($calc['salary_type'])->toBe('fixed')
        ->and($calc['total'])->toBe(6000000.0);
});

it('soatbay rejimda o\'tkazilgan darslar soniga ko\'paytiradi', function () {
    $teacher = Teacher::factory()->perLesson(80_000)->create();

    Lesson::factory()->count(12)->create([
        'teacher_id' => $teacher->id,
        'date' => now()->startOfMonth()->addDays(2)->toDateString(),
    ]);

    $calc = $this->salaries->calculate($teacher, now()->year, now()->month);

    expect($calc['lessons_count'])->toBe(12)
        ->and($calc['total'])->toBe(960000.0);
});

it('bekor qilingan darslarni hisobga olmaydi', function () {
    $teacher = Teacher::factory()->perLesson(100_000)->create();

    Lesson::factory()->count(3)->create(['teacher_id' => $teacher->id, 'date' => now()->toDateString()]);
    Lesson::factory()->count(2)->create([
        'teacher_id' => $teacher->id, 'date' => now()->toDateString(), 'status' => Lesson::STATUS_CANCELED,
    ]);

    expect($this->salaries->calculate($teacher, now()->year, now()->month)['total'])->toBe(300000.0);
});

it('mukofot va ushlab qolishni hisobga oladi', function () {
    $teacher = Teacher::factory()->create(['fixed_salary' => 5_000_000]);

    $salary = $this->salaries->generate($teacher, now()->year, now()->month, bonus: 500_000, deduction: 200_000);

    expect((float) $salary->total_amount)->toBe(5300000.0)
        ->and($salary->status)->toBe('pending');
});

it('bir davr uchun ikkinchi yozuv yaratmaydi', function () {
    $teacher = Teacher::factory()->create();

    $this->salaries->generate($teacher, 2026, 9);
    $this->salaries->generate($teacher, 2026, 9);

    expect($teacher->salaries()->count())->toBe(1);
});

it('to\'langan deb belgilaydi', function () {
    $teacher = Teacher::factory()->create();
    $salary = $this->salaries->generate($teacher, 2026, 9);

    expect($this->salaries->markPaid($salary)->status)->toBe('paid');
});
