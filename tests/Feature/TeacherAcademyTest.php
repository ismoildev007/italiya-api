<?php

use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\NotificationEvent;
use App\Models\Teacher;
use App\Models\VocabDeck;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedRbac();
    $this->user = actingAsUser('teacher');
    $this->teacher = Teacher::factory()->create(['user_id' => $this->user->id]);
});

it('AI (Gemini) orqali IELTS uslubidagi test generatsiya qiladi', function () {
    config(['academy.features.ai' => true, 'services.gemini.key' => 'k', 'services.ai.generator' => 'gemini']);

    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode([
        'title' => 'A1 test',
        'sections' => [
            ['skill' => 'grammar', 'questions' => [
                ['body' => 'Come si chiama?', 'type' => 'single_choice', 'options' => [['body' => 'Anna', 'is_correct' => true], ['body' => 'Luca', 'is_correct' => false]]],
            ]],
            ['skill' => 'reading', 'passage' => 'Testo…', 'questions' => [
                ['body' => 'Anna è italiana.', 'type' => 'true_false', 'correct_answer' => 'true'],
            ]],
        ],
    ])]]]]]])]);

    $data = $this->postJson('/api/v1/tests/generate', ['level' => 'A1', 'skills' => ['grammar', 'reading'], 'question_count' => 1, 'kind' => 'mock'])
        ->assertOk()->json('data');

    expect($data['sections'])->toHaveCount(2)
        ->and($data['sections'][0]['skill'])->toBe('grammar')
        ->and($data['sections'][1]['questions'][0]['type'])->toBe('true_false')
        ->and($data['provider'])->toBe('gemini');

    // O'qituvchi tahrirlab saqlaydi — mock: 1 urinish, audio 1 marta (config)
    $data['sections'][0]['questions'][] = ['body' => 'Qo\'shimcha', 'type' => 'short_text', 'correct_answer' => 'sì'];

    $saved = $this->postJson('/api/v1/tests', ['title' => 'A1 Mock', 'level' => 'A1', 'kind' => 'mock', 'sections' => $data['sections'], 'generated_by' => 'ai'])
        ->assertCreated()->json('data');

    expect($saved['question_count'])->toBe(3)
        ->and($saved['effective_max_attempts'])->toBe(1)
        ->and($saved['effective_max_plays'])->toBe(1)
        ->and($saved['sections'])->toHaveCount(2);
});

it('uy ishi beriladi, topshiriladi va baholanadi (xabarlar bilan)', function () {
    $student = makeStudent('A1');

    $hw = $this->postJson('/api/v1/homeworks', ['title' => 'Insho', 'class_id' => $student->class_id, 'due_at' => now()->addDays(3)->toDateTimeString()])
        ->assertCreated()->json('data');

    expect(NotificationEvent::where('type', NotificationEvent::TYPE_HOMEWORK_ASSIGNED)->count())->toBe(1);

    loginAsStudent($student);
    $this->postJson("/api/v1/learn/homeworks/{$hw['id']}/submit", ['text' => 'Mi chiamo…', 'file' => UploadedFile::fake()->create('insho.pdf', 10)])->assertCreated();
    $this->getJson('/api/v1/learn/homeworks')->assertOk()->assertJsonPath('data.0.my_submission.status', 'pending');

    $this->actingAs($this->user, 'sanctum');
    $submission = HomeworkSubmission::first();
    $this->putJson("/api/v1/homework-submissions/{$submission->id}/grade", ['score' => 85, 'feedback' => 'Bravo'])
        ->assertOk()->assertJsonPath('data.score', 85);

    expect(Homework::first()->submissions()->where('status', 'checked')->count())->toBe(1)
        ->and(NotificationEvent::where('type', NotificationEvent::TYPE_HOMEWORK_GRADED)->count())->toBe(1);
});

it('lug\'at to\'plamiga CSV import qiladi', function () {
    $deck = VocabDeck::create(['school_id' => 1, 'title' => 'Import', 'level' => 'A1']);
    $csv = "So'z,Tarjima,English\nBuongiorno,Xayrli kun,Good morning\nGrazie,Rahmat,Thanks\n";

    $this->postJson("/api/v1/decks/{$deck->id}/import", ['file' => UploadedFile::fake()->createWithContent('words.csv', $csv)])
        ->assertOk()->assertJsonPath('data.cards_count', 2)->assertJsonPath('data.cards.1.translations.en', 'Thanks');
});

it('o\'qituvchi darslik yaratadi, tinglash o\'chirilgan — listening darslik qabul qilinmaydi', function () {
    $pdf = fn () => UploadedFile::fake()->create('dars.pdf', 20, 'application/pdf');

    $this->postJson('/api/v1/units', ['level' => 'A1', 'skill' => 'listening', 'title' => 'Ascolto 1', 'pdf' => $pdf(), 'available_on' => today()->toDateString()])->assertStatus(422);

    $unit = $this->postJson('/api/v1/units', ['level' => 'A1', 'skill' => 'reading', 'title' => 'Lettura 1', 'content' => '# Testo', 'is_published' => true, 'pdf' => $pdf(), 'available_on' => today()->toDateString()])
        ->assertCreated()->json('data');

    $student = loginAsStudent(makeStudent('A1'));
    $this->getJson('/api/v1/learn/units')->assertOk()->assertJsonPath('data.0.id', $unit['id'])->assertJsonPath('data.0.has_media', false);
    $this->postJson("/api/v1/learn/units/{$unit['id']}/play")->assertStatus(422);
    $this->postJson("/api/v1/learn/units/{$unit['id']}/complete")->assertOk();

    // Boshqa darajadagi o'quvchi ko'rmaydi
    loginAsStudent(makeStudent('B2'));
    $this->getJson('/api/v1/learn/units')->assertOk()->assertJsonCount(0, 'data');
});

it('o\'quvchi darajasini o\'zgartiradi va tarix yoziladi', function () {
    $student = makeStudent('A1');
    actingAsUser('admin');

    $this->putJson("/api/v1/students/{$student->id}/level", ['level' => 'A2', 'reason' => 'promoted'])->assertOk()->assertJsonPath('data.level', 'A2');
    $this->getJson("/api/v1/students/{$student->id}/level-history")->assertOk()->assertJsonPath('data.0.to_level', 'A2');
    expect(NotificationEvent::where('type', NotificationEvent::TYPE_LEVEL_CHANGED)->count())->toBe(1); // ota-ona yo'q — faqat o'quvchiga
});

it('AI yoqilgan, lekin kalit bo\'lmasa lokal zaxira test, darslik va lug\'at generatsiya qiladi', function () {
    config(['academy.features.ai' => true, 'services.gemini.key' => null, 'services.openai.key' => null, 'services.anthropic.key' => null]);

    $test = $this->postJson('/api/v1/tests/generate', ['level' => 'B1', 'skills' => ['reading', 'writing', 'speaking', 'grammar'], 'question_count' => 3, 'kind' => 'mock'])
        ->assertOk()->json('data');

    expect($test['provider'])->toBe('local')
        ->and($test['sections'])->toHaveCount(4)
        ->and($test['sections'][0]['passage'])->not->toBeEmpty()
        ->and(collect($test['sections'][2]['questions'])->pluck('type')->unique()->all())->toBe(['speaking']);

    $this->postJson('/api/v1/tests', ['title' => 'B1 Mock', 'level' => 'B1', 'kind' => 'mock', 'sections' => $test['sections']])->assertCreated();

    $unit = $this->postJson('/api/v1/units/generate', ['level' => 'A2', 'skill' => 'reading'])->assertOk()->json('data');
    expect($unit['provider'])->toBe('local')->and($unit['content'])->toContain('Roma');

    $cards = $this->postJson('/api/v1/decks/generate', ['level' => 'A1', 'count' => 5, 'languages' => ['uz', 'ru']])->assertOk()->json('data.cards');
    expect($cards)->toHaveCount(5)->and($cards[0]['translations'])->toHaveKeys(['uz', 'ru']);

    $this->getJson('/api/v1/ai/status')->assertOk()->assertJsonPath('data.fallback.provider', 'local');
});

it('tashqi AI xato bersa ham generatsiya lokal zaxira bilan ishlaydi', function () {
    config(['academy.features.ai' => true, 'services.gemini.key' => 'k', 'services.ai.generator' => 'gemini']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response('error', 500)]);

    $this->postJson('/api/v1/tests/generate', ['level' => 'A1', 'skills' => ['reading'], 'question_count' => 2])
        ->assertOk()->assertJsonPath('data.provider', 'local');
});

it('essay lokal evristika bilan baholanadi (AI yoqiq, kalitsiz)', function () {
    config(['academy.features.ai' => true, 'services.gemini.key' => null, 'services.openai.key' => null]);
    $student = loginAsStudent(makeStudent('A1'));
    $template = makeTemplate('A1', 'practice');

    $view = $this->postJson("/api/v1/learn/practice/{$template->id}/start")->json('data');
    $essay = collect($view['sections'])->flatMap(fn ($s) => $s['questions'])->firstWhere('type', 'essay');

    $result = $this->postJson("/api/v1/learn/attempts/{$view['attempt']['id']}/submit", [
        'answers' => [['question_id' => $essay['id'], 'answer_text' => 'Mi chiamo Ali. Ho venti anni e sono di Tashkent. La mia famiglia è grande: ho due fratelli e una sorella. Studio italiano perché voglio lavorare in Italia. Nel tempo libero leggo e ascolto la musica.']],
    ])->assertOk()->json('data');

    $q = collect($result['sections'])->flatMap(fn ($s) => $s['questions'])->firstWhere('type', 'essay');
    expect($result['attempt']['status'])->toBe('graded')
        ->and($q['answer']['score'])->toBeGreaterThan(2)
        ->and($q['answer']['ai_feedback']['provider'])->toBe('local');
});

it('noto\'g\'ri id (undefined) uchun SQL xatosi emas, 404 qaytadi', function () {
    $this->getJson('/api/v1/students/undefined')->assertNotFound()->assertJsonPath('message', 'Manzil topilmadi.');
    $this->getJson('/api/v1/tests/undefined')->assertNotFound();
    $this->getJson('/api/v1/groups/abc')->assertNotFound();
});

it('AI o\'chirilgan bo\'lsa generatsiya 422 qaytaradi va ai/status enabled=false', function () {
    $this->postJson('/api/v1/tests/generate', ['level' => 'A1', 'skills' => ['reading'], 'question_count' => 2])->assertStatus(422);
    $this->postJson('/api/v1/units/generate', ['level' => 'A1', 'skill' => 'reading'])->assertStatus(422);
    $this->getJson('/api/v1/ai/status')->assertOk()->assertJsonPath('data.enabled', false)->assertJsonPath('data.audio_enabled', false);
    // listening / dictation qabul qilinmaydi
    $this->postJson('/api/v1/tests', ['title' => 'x', 'level' => 'A1', 'sections' => [['skill' => 'listening', 'questions' => [['body' => 'q', 'type' => 'dictation']]]]])->assertStatus(422);
});
