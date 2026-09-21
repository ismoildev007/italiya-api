<?php

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TestAssignment;
use App\Models\TestQuestion;
use App\Models\TestQuestionOption;
use App\Models\TestSection;
use App\Models\TestTemplate;
use App\Models\User;

/**
 * Akademiya testlari uchun yordamchilar: guruh + o'quvchi (telefon/parol) + IELTS uslubidagi shablon.
 */
function makeStudent(string $level = 'A1', array $attrs = []): Student
{
    $group = SchoolClass::factory()->create(['level' => $level, 'name' => "{$level}-".fake()->unique()->numberBetween(1, 9999)]);
    $user = User::factory()->create(['phone' => '+99890'.fake()->unique()->numberBetween(1000000, 9999999), 'password' => 'parol123']);
    $user->roles()->sync(['student']);

    return Student::factory()->create(['class_id' => $group->id, 'level' => $level, 'user_id' => $user->id, 'phone' => $user->phone] + $attrs);
}

function loginAsStudent(Student $student): Student
{
    test()->actingAs($student->user, 'sanctum');

    return $student;
}

/** Listening (choice + dictation) va Writing (gap + essay) bo'limli shablon. */
function makeTemplate(string $level = 'A1', string $kind = 'mock', array $attrs = []): TestTemplate
{
    $teacher = Teacher::factory()->create();
    $template = TestTemplate::create([
        'school_id' => config('school.id'),
        'subject_id' => Subject::factory()->create()->id,
        'teacher_id' => $teacher->id,
        'level' => $level,
        'kind' => $kind,
        'title' => "{$level} {$kind}",
        'duration_minutes' => 30,
        'is_published' => true,
        'auto_grade_ai' => true,
    ] + $attrs);

    $listening = TestSection::create(['school_id' => 1, 'test_template_id' => $template->id, 'order' => 1, 'skill' => 'listening', 'title' => 'Ascolto', 'media_script' => 'Mi chiamo Marco.', 'media_path' => 'tests/x.mp3', 'time_limit_minutes' => 10]);
    $writing = TestSection::create(['school_id' => 1, 'test_template_id' => $template->id, 'order' => 2, 'skill' => 'writing', 'title' => 'Scrittura', 'time_limit_minutes' => 20]);

    $q1 = TestQuestion::create(['school_id' => 1, 'test_template_id' => $template->id, 'test_section_id' => $listening->id, 'order' => 1, 'body' => 'Come si chiama?', 'type' => 'single_choice', 'skill' => 'listening', 'score' => 1]);
    TestQuestionOption::create(['test_question_id' => $q1->id, 'order' => 1, 'body' => 'Marco', 'is_correct' => true]);
    TestQuestionOption::create(['test_question_id' => $q1->id, 'order' => 2, 'body' => 'Luca', 'is_correct' => false]);

    TestQuestion::create(['school_id' => 1, 'test_template_id' => $template->id, 'test_section_id' => $listening->id, 'order' => 2, 'body' => 'Scrivi la frase.', 'type' => 'dictation', 'skill' => 'listening', 'score' => 1, 'correct_answer' => 'Mi chiamo Marco.']);
    TestQuestion::create(['school_id' => 1, 'test_template_id' => $template->id, 'test_section_id' => $writing->id, 'order' => 1, 'body' => 'Io _____ Ali.', 'type' => 'gap', 'skill' => 'writing', 'score' => 1, 'correct_answer' => 'sono']);
    TestQuestion::create(['school_id' => 1, 'test_template_id' => $template->id, 'test_section_id' => $writing->id, 'order' => 2, 'body' => 'Sofia vive a Firenze.', 'type' => 'true_false', 'skill' => 'writing', 'score' => 1, 'correct_answer' => 'true']);
    TestQuestion::create(['school_id' => 1, 'test_template_id' => $template->id, 'test_section_id' => $writing->id, 'order' => 3, 'body' => 'Scrivi di te.', 'type' => 'essay', 'skill' => 'writing', 'score' => 5, 'model_answer' => 'Mi chiamo…']);

    $template->recalculateScore();

    return $template->fresh();
}

function assignTo(TestTemplate $template, Student $student): TestAssignment
{
    return TestAssignment::create([
        'school_id' => config('school.id'),
        'test_template_id' => $template->id,
        'class_id' => $student->class_id,
        'teacher_id' => $template->teacher_id,
        'target' => 'class',
        'starts_at' => now()->subHour(),
        'status' => 'active',
    ]);
}
