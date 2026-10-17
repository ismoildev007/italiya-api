<?php

use App\Models\MediaPlay;
use App\Models\TestAttempt;
use Illuminate\Support\Facades\Http;

beforeEach(fn () => seedRbac());

it('o\'quvchi telefon + parol bilan kiradi va qurilmaga bog\'lanadi', function () {
    $student = makeStudent('A2');

    $this->postJson('/api/v1/login', ['login' => $student->phone, 'password' => 'parol123', 'device_id' => 'phone-1'])
        ->assertOk()
        ->assertJsonPath('data.student.level', 'A2');

    expect($student->fresh()->device_id)->toBe('phone-1');

    // Boshqa qurilmadan kirish rad etiladi
    $this->postJson('/api/v1/login', ['login' => $student->phone, 'password' => 'parol123', 'device_id' => 'phone-2'])
        ->assertStatus(422);

    // Telefon formati bo'shliq bilan bo'lsa ham topiladi
    $pretty = preg_replace('/^\+998(\d\d)(\d{3})(\d{2})(\d{2})$/', '+998 $1 $2-$3-$4', $student->phone);
    $this->postJson('/api/v1/login', ['login' => $pretty, 'password' => 'parol123', 'device_id' => 'phone-1'])->assertOk();
});

it('o\'quvchi faqat o\'z darajasidagi biriktirilgan testlarni ko\'radi', function () {
    $student = loginAsStudent(makeStudent('B1'));
    assignTo(makeTemplate('B1', 'mock'), $student);
    assignTo(makeTemplate('C1', 'mock'), makeStudent('C1'));

    $this->getJson('/api/v1/learn/tests')
        ->assertOk()
        ->assertJsonCount(1, 'data.assigned')
        ->assertJsonPath('data.assigned.0.level', 'B1')
        ->assertJsonPath('data.assigned.0.state.limit', 1);

    $this->getJson('/api/v1/learn/dashboard')->assertOk()->assertJsonPath('data.student.level', 'B1');
});

it('mock testni faqat 1 marta topshirish mumkin', function () {
    $student = loginAsStudent(makeStudent('A1'));
    $template = makeTemplate('A1', 'mock');
    $assignment = assignTo($template, $student);

    $view = $this->postJson("/api/v1/learn/assignments/{$assignment->id}/start")->assertOk()->json('data');
    $attemptId = $view['attempt']['id'];
    $section = $view['sections'][0];

    expect($section['has_media'])->toBeFalse()->and(MediaPlay::count())->toBe(0);

    // Javoblar va topshirish
    $questions = collect($view['sections'])->flatMap(fn ($s) => $s['questions']);
    $choice = $questions->firstWhere('type', 'single_choice');
    $correctOption = collect($choice['options'])->firstWhere('body', 'Marco');

    $this->postJson("/api/v1/learn/attempts/{$attemptId}/answer", ['question_id' => $choice['id'], 'selected_option_ids' => [$correctOption['id']]])->assertOk();
    foreach ($questions->where('type', 'gap') as $gap) {
        $this->postJson("/api/v1/learn/attempts/{$attemptId}/answer", ['question_id' => $gap['id'], 'answer_text' => $gap['body'] === 'Io _____ Ali.' ? 'sono' : 'chiamo'])->assertOk();
    }
    $this->postJson("/api/v1/learn/attempts/{$attemptId}/answer", ['question_id' => $questions->firstWhere('type', 'true_false')['id'], 'answer_text' => 'false'])->assertOk();

    $result = $this->postJson("/api/v1/learn/attempts/{$attemptId}/submit")->assertOk()->json('data');

    // 3 ta avtomatik to'g'ri (1+1+1), true_false xato; AI o'chiq → essay o'qituvchi tekshiruvini kutadi
    expect((float) $result['attempt']['total_score'])->toBe(3.0)
        ->and($result['attempt']['status'])->toBe(TestAttempt::STATUS_SUBMITTED);

    // Mock: ikkinchi urinish taqiqlanadi
    $this->postJson("/api/v1/learn/assignments/{$assignment->id}/start")->assertStatus(422);

    $this->getJson('/api/v1/learn/tests')->assertJsonPath('data.assigned.0.state.remaining', 0);
});

it('mashq testi cheksiz, essay Gemini orqali baholanadi va ko\'nikma progressi yangilanadi', function () {
    config(['academy.features.ai' => true, 'services.gemini.key' => 'test-key', 'services.ai.evaluator' => 'gemini']);

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode([
                'score_percent' => 80,
                'criteria' => ['task_response' => 80, 'grammar' => 75, 'vocabulary' => 85, 'coherence' => 80],
                'errors' => [],
                'corrected_text' => 'Mi chiamo Ali.',
                'feedback' => 'Yaxshi ish!',
            ])]]]]],
        ]),
    ]);

    $student = loginAsStudent(makeStudent('A1'));
    $template = makeTemplate('A1', 'practice');

    $view = $this->postJson("/api/v1/learn/practice/{$template->id}/start")->assertOk()->json('data');
    $attemptId = $view['attempt']['id'];
    $essay = collect($view['sections'])->flatMap(fn ($s) => $s['questions'])->firstWhere('type', 'essay');

    $result = $this->postJson("/api/v1/learn/attempts/{$attemptId}/submit", [
        'answers' => [['question_id' => $essay['id'], 'answer_text' => 'Mi chiamo Ali e sono di Tashkent.']],
    ])->assertOk()->json('data');

    expect($result['attempt']['status'])->toBe(TestAttempt::STATUS_GRADED)
        ->and((float) $result['attempt']['total_score'])->toBe(4.0); // 80% × 5 ball

    $writing = collect($result['sections'])->firstWhere('skill', 'writing');
    $essayView = collect($writing['questions'])->firstWhere('type', 'essay');
    expect($essayView['answer']['ai_feedback']['feedback'])->toBe('Yaxshi ish!')
        ->and($essayView['correct_answer'])->toBeNull(); // practice: show_answers

    // Ikkinchi urinish ruxsat etiladi (practice — cheksiz)
    $this->postJson("/api/v1/learn/practice/{$template->id}/start")->assertOk()->assertJsonPath('data.attempt.attempt_no', 2);

    $skills = $this->getJson('/api/v1/learn/dashboard')->json('data.skills');
    expect($skills['writing']['attempts'])->toBeGreaterThanOrEqual(1);
});

it('vaqt tugagan urinishga javob berib bo\'lmaydi', function () {
    $student = loginAsStudent(makeStudent('A1'));
    $assignment = assignTo(makeTemplate('A1', 'test'), $student);
    $attemptId = $this->postJson("/api/v1/learn/assignments/{$assignment->id}/start")->json('data.attempt.id');

    TestAttempt::whereKey($attemptId)->update(['expires_at' => now()->subMinute()]);

    $this->postJson("/api/v1/learn/attempts/{$attemptId}/answer", ['question_id' => 1, 'answer_text' => 'x'])->assertStatus(422);
    expect(TestAttempt::find($attemptId)->isFinished())->toBeTrue();
});
