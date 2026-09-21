<?php

use App\Models\Certificate;
use App\Models\Game;
use App\Models\NotificationEvent;
use App\Models\TestAttempt;
use App\Models\VocabCard;
use App\Models\VocabDeck;
use Illuminate\Support\Carbon;

beforeEach(fn () => seedRbac());

function makeDeck(string $level = 'A1'): VocabDeck
{
    $deck = VocabDeck::create(['school_id' => 1, 'title' => 'Test', 'level' => $level, 'track' => 'general', 'is_published' => true]);

    foreach ([['Ciao', 'Salom', 'Hi'], ['Grazie', 'Rahmat', 'Thanks'], ['Casa', 'Uy', 'House'], ['Pane', 'Non', 'Bread']] as $i => [$it, $uz, $en]) {
        VocabCard::create(['school_id' => 1, 'vocab_deck_id' => $deck->id, 'order' => $i + 1, 'word' => $it, 'translations' => ['uz' => $uz, 'en' => $en]]);
    }

    $deck->recount();

    return $deck;
}

it('sertifikat oyning 25-sanasidan keyin va oyiga faqat 1 marta beriladi', function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 10));
    $student = loginAsStudent(makeStudent('B1'));
    $assignment = assignTo(makeTemplate('B1', 'test'), $student);

    TestAttempt::create(['school_id' => 1, 'test_assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'graded', 'percent' => 75, 'total_score' => 3, 'max_score' => 4, 'submitted_at' => now()]);

    $this->getJson('/api/v1/learn/certificate/eligibility')->assertOk()->assertJsonPath('data.eligible', false);
    $this->postJson('/api/v1/learn/certificate')->assertStatus(422);

    Carbon::setTestNow(Carbon::create(2026, 9, 26));

    $this->getJson('/api/v1/learn/certificate/eligibility')->assertOk()->assertJsonPath('data.eligible', true);

    $cert = $this->postJson('/api/v1/learn/certificate')->assertCreated()->json('data');
    expect($cert['level'])->toBe('B1')->and($cert['serial'])->toStartWith('ITA-202609-');

    // Ikkinchi marta — 422
    $this->postJson('/api/v1/learn/certificate')->assertStatus(422);
    expect(Certificate::count())->toBe(1);

    // Ommaviy tekshirish va HTML
    $this->getJson("/api/v1/certificates/verify/{$cert['verify_code']}")->assertOk()->assertJsonPath('data.valid', true);
    $this->get("/api/v1/certificates/{$cert['serial']}/html")->assertOk()->assertSee($student->full_name);

    // Ota-ona va o'quvchiga xabar navbatga qo'yildi
    expect(NotificationEvent::where('type', NotificationEvent::TYPE_CERTIFICATE)->count())->toBeGreaterThanOrEqual(1);

    Carbon::setTestNow();
});

it('o\'yin: raundlar, ball va liderbord', function () {
    $student = loginAsStudent(makeStudent('A1', ['preferred_language' => 'en']));
    $deck = makeDeck('A1');
    $game = Game::create(['school_id' => 1, 'vocab_deck_id' => $deck->id, 'title' => 'Game', 'type' => 'choice', 'level' => 'A1', 'card_count' => 3, 'round_types' => ['it-to-tr'], 'points_per_correct' => 10]);

    $this->getJson('/api/v1/learn/games')->assertOk()->assertJsonCount(1, 'data');

    $session = $this->postJson("/api/v1/learn/games/{$game->id}/start")->assertOk()->json('data');
    expect($session['total_rounds'])->toBe(3)
        ->and($session['rounds'][0])->not->toHaveKey('answer') // javob yashirin
        ->and($session['rounds'][0]['answer_language'])->toBe('en');

    $round = $session['rounds'][0];
    $correct = VocabCard::find($round['card_id'])->translation('en');

    $this->postJson("/api/v1/learn/game-sessions/{$session['id']}/answer", ['round_no' => $round['no'], 'answer' => 'xato'])
        ->assertOk()->assertJsonPath('data.correct', false)->assertJsonPath('data.points', 0);

    $this->postJson("/api/v1/learn/game-sessions/{$session['id']}/answer", ['round_no' => $round['no'], 'answer' => $correct])
        ->assertOk()->assertJsonPath('data.correct', true)->assertJsonPath('data.points', 10);

    $this->postJson("/api/v1/learn/game-sessions/{$session['id']}/finish")->assertOk()->assertJsonPath('data.score', 10);

    expect($student->fresh()->speech_game_score)->toBe(10);

    $this->getJson('/api/v1/learn/leaderboard')->assertOk()->assertJsonPath('data.0.student_id', $student->id);
});

it('kartochkalar tanlangan tilda qaytadi', function () {
    loginAsStudent(makeStudent('A2'));
    $deck = makeDeck('A1'); // pastki daraja ham ko'rinadi

    $this->getJson('/api/v1/learn/decks')->assertOk()->assertJsonCount(1, 'data');

    $this->getJson("/api/v1/learn/decks/{$deck->id}/cards?lang=en")
        ->assertOk()
        ->assertJsonPath('data.cards.0.front', 'Ciao')
        ->assertJsonPath('data.cards.0.back', 'Hi');
});

it('sozlamalar va tarjima tili saqlanadi', function () {
    $student = loginAsStudent(makeStudent('A1'));

    $this->putJson('/api/v1/settings', ['theme' => 'dark', 'sound' => false, 'preferred_language' => 'ru'])
        ->assertOk()->assertJsonPath('data.theme', 'dark')->assertJsonPath('data.preferred_language', 'ru');

    expect($student->fresh()->preferred_language)->toBe('ru');
});
