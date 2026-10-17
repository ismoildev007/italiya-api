<?php

use App\Models\Duel;
use App\Models\PushToken;
use App\Models\VocabCard;
use App\Models\VocabDeck;
use Illuminate\Support\Carbon;

beforeEach(function () {
    seedRbac();
    config(['broadcasting.default' => 'log']);
});

function seedDeck(string $level = 'A1', int $count = 8): VocabDeck
{
    $deck = VocabDeck::create(['school_id' => 1, 'title' => "Deck {$level}", 'level' => $level, 'track' => 'general', 'is_published' => true]);
    $words = [['Ciao', 'Salom'], ['Grazie', 'Rahmat'], ['Casa', 'Uy'], ['Pane', 'Non'], ['Acqua', 'Suv'], ['Libro', 'Kitob'], ['Scuola', 'Maktab'], ['Amico', 'Do\'st']];
    foreach (array_slice($words, 0, $count) as $i => [$it, $uz]) {
        VocabCard::create(['school_id' => 1, 'vocab_deck_id' => $deck->id, 'order' => $i + 1, 'word' => $it, 'translations' => ['uz' => $uz, 'en' => $it]]);
    }
    $deck->recount();

    return $deck;
}

it('dars yo\'li: xarita, dars boshlash, mashqlarga javob, yakunlash — progress va XP', function () {
    $student = loginAsStudent(makeStudent('A1'));
    seedDeck('A1');
    makeTemplate('A1', 'practice');

    $map = $this->getJson('/api/v1/learn/path')->assertOk()->json('data');
    expect($map['current_level'])->toBe('A1')
        ->and($map['levels'][0]['unlocked'])->toBeTrue()
        ->and($map['levels'][1]['unlocked'])->toBeFalse()
        ->and(collect($map['levels'][0]['skills'])->pluck('skill')->all())->not->toContain('listening');

    $this->getJson('/api/v1/learn/path/A1/grammar')->assertOk()->assertJsonPath('data.level', 'A1');
    $this->postJson('/api/v1/learn/path/B2/grammar/start')->assertStatus(403);
    $this->postJson('/api/v1/learn/path/A1/listening/start')->assertStatus(422);

    $lesson = $this->postJson('/api/v1/learn/path/A1/grammar/start')->assertOk()->json('data');
    expect($lesson['total'])->toBeGreaterThanOrEqual(3)
        ->and(collect($lesson['exercises'])->pluck('type')->unique()->all())->toContain('match');

    foreach ($lesson['exercises'] as $e) {
        expect($e)->not->toHaveKey('answer');
    }

    $match = collect($lesson['exercises'])->firstWhere('type', 'match');
    $pairs = collect($match['left'])->mapWithKeys(fn ($l) => [$l['id'] => $l['id']])->all();
    $this->postJson("/api/v1/learn/lessons/{$lesson['id']}/answer", ['no' => $match['no'], 'answer' => $pairs])->assertOk()->assertJsonPath('data.correct', true);

    $choice = collect($lesson['exercises'])->firstWhere('type', 'choice');
    $this->postJson("/api/v1/learn/lessons/{$lesson['id']}/answer", ['no' => $choice['no'], 'answer' => 'xato javob'])->assertOk()->assertJsonPath('data.correct', false);
    $this->postJson("/api/v1/learn/lessons/{$lesson['id']}/answer", ['no' => $choice['no'], 'answer' => 'x'])->assertStatus(422);

    $result = $this->postJson("/api/v1/learn/lessons/{$lesson['id']}/finish")->assertOk()->json('data');
    expect($result['finished'])->toBeTrue()->and($result['xp_earned'])->toBeGreaterThan(0)->and($result['skill_percent'])->toBeGreaterThan(0)
        ->and($student->fresh()->xp)->toBe($result['xp_earned']);

    $this->getJson('/api/v1/learn/path')->assertOk()->assertJsonPath('data.xp', $result['xp_earned']);
});

it('duel: bot bilan — birinchi to\'g\'ri javob raundni yutadi, bot kechikib javob beradi', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 14, 10, 0, 0));
    $student = loginAsStudent(makeStudent('B1'));
    seedDeck('B1');

    $this->getJson('/api/v1/learn/duels')->assertOk()->assertJsonCount(3, 'data.bots');

    $duel = $this->postJson('/api/v1/learn/duels', [])->assertCreated()->json('data');
    expect($duel['status'])->toBe('waiting')->and($duel['invite_code'])->toHaveLength(6);

    $state = $this->postJson("/api/v1/learn/duels/{$duel['id']}/bot", ['bot_level' => 'hard'])->assertOk()->json('data');
    expect($state['status'])->toBe('active')->and($state['opponent']['is_bot'])->toBeTrue()->and($state['question']['no'])->toBe(1)->and($state['seconds_left'])->toBe(20);

    // To'g'ri javobni bilish uchun bazadan olamiz
    $model = Duel::find($duel['id']);
    $q1 = collect($model->questions)->firstWhere('no', 1);

    // O'quvchi darhol to'g'ri javob beradi — raund uniki (bot hali javob bermagan: kuchli bot 2–5 s)
    $r = $this->postJson("/api/v1/learn/duels/{$duel['id']}/answer", ['no' => 1, 'answer' => $q1['answer']])->assertOk()->json('data');
    expect($r['correct'])->toBeTrue()->and($r['won_round'])->toBeTrue()->and($r['state']['current_no'])->toBe(2)->and($r['state']['host_score'])->toBe(1);

    // 2-raund: o'quvchi kutadi, bot 6 soniyadan keyin javob bergan bo'ladi (resolved lazy)
    Carbon::setTestNow(now()->addSeconds(6));
    $state = $this->getJson("/api/v1/learn/duels/{$duel['id']}")->assertOk()->json('data');
    $round2 = collect($state['rounds'])->firstWhere('no', 2);
    expect($round2['opponent'])->not->toBeNull();

    // Raund vaqti tugasa — keyingi raund
    Carbon::setTestNow(now()->addSeconds(25));
    $state = $this->getJson("/api/v1/learn/duels/{$duel['id']}")->assertOk()->json('data');
    expect($state['status'] === 'finished' || $state['current_no'] >= 3)->toBeTrue();

    // Noto'g'ri javob — raundda qulflanadi
    if ($state['status'] === 'active') {
        $this->postJson("/api/v1/learn/duels/{$duel['id']}/answer", ['no' => $state['current_no'], 'answer' => 'noto\'g\'ri'])->assertOk()->assertJsonPath('data.correct', false);
        $this->postJson("/api/v1/learn/duels/{$duel['id']}/answer", ['no' => $state['current_no'], 'answer' => 'yana'])->assertStatus(422);
    }

    // Barcha raundlar o'tib ketsin
    Carbon::setTestNow(now()->addMinutes(10));
    $final = $this->getJson("/api/v1/learn/duels/{$duel['id']}")->assertOk()->json('data');
    expect($final['status'])->toBe('finished')->and($final['winner'])->toBeIn(['host', 'opponent', 'draw'])->and($final['xp'])->toHaveKeys(['host', 'opponent']);

    $fresh = $student->fresh();
    expect($fresh->duel_wins + $fresh->duel_losses)->toBeLessThanOrEqual(1);

    Carbon::setTestNow();
});

it('duel: sherikni taklif qilish va kod bilan qo\'shilish', function () {
    $host = makeStudent('A1');
    $guest = makeStudent('A1');
    seedDeck('A1');

    loginAsStudent($host);
    $this->getJson('/api/v1/learn/duels/opponents')->assertOk()->assertJsonPath('data.0.id', $guest->id);
    $duel = $this->postJson('/api/v1/learn/duels', ['opponent_student_id' => $guest->id])->assertCreated()->json('data');

    loginAsStudent($guest);
    $this->getJson('/api/v1/learn/duels')->assertOk()->assertJsonPath('data.invites.0.id', $duel['id']);
    $state = $this->postJson('/api/v1/learn/duels/join', ['code' => strtolower($duel['invite_code'])])->assertOk()->json('data');
    expect($state['status'])->toBe('active')->and($state['you'])->toBe('opponent')->and($state['opponent']['id'])->toBe($guest->id);

    // Begona o'quvchi kira olmaydi
    loginAsStudent(makeStudent('A1'));
    $this->getJson("/api/v1/learn/duels/{$duel['id']}")->assertStatus(403);
});

it('qo\'llanma rol va ruxsatga qarab filtrlanadi', function () {
    actingAsUser('teacher');
    $teacher = $this->getJson('/api/v1/guide')->assertOk()->json('data');
    $keys = collect($teacher['sections'])->pluck('key');
    expect($keys)->toContain('tests')->and($keys)->not->toContain('finance')->and($keys)->not->toContain('learn_duel');

    loginAsStudent(makeStudent('A1'));
    $student = $this->getJson('/api/v1/guide')->assertOk()->json('data');
    $keys = collect($student['sections'])->pluck('key');
    expect($keys)->toContain('learn_duel')->and($keys)->not->toContain('tests')->and($student['features']['ai'])->toBeFalse();
});

it('duel sheriklari: online birinchi, offline pastda; heartbeat ishlaydi; push token saqlanadi', function () {
    $me = makeStudent('A1');
    $online = makeStudent('A1', ['firstname' => 'Zafar', 'last_activity_at' => now()->subMinute()]);
    $offline = makeStudent('A1', ['firstname' => 'Aziz', 'last_activity_at' => now()->subHours(5)]);
    $never = makeStudent('A1', ['firstname' => 'Bobur', 'last_activity_at' => null]);

    loginAsStudent($me);
    $list = $this->getJson('/api/v1/learn/duels/opponents')->assertOk()->json('data');
    expect($list[0]['id'])->toBe($online->id)->and($list[0]['online'])->toBeTrue()
        ->and(collect($list)->pluck('online')->all())->toBe([true, false, false]);

    // Heartbeat: offline o'quvchi kirdi → online
    loginAsStudent($offline);
    $this->postJson('/api/v1/learn/presence')->assertOk()->assertJsonPath('data.online', true);

    loginAsStudent($me);
    $list = $this->getJson('/api/v1/learn/duels/opponents')->assertOk()->json('data');
    expect(collect($list)->where('online', true)->pluck('id')->all())->toEqualCanonicalizing([$online->id, $offline->id]);

    // Push token
    $this->postJson('/api/v1/push/token', ['token' => 'fcm-token-123'])->assertOk();
    $this->postJson('/api/v1/push/token', ['token' => 'fcm-token-123'])->assertOk();
    expect(PushToken::count())->toBe(1);
    $this->getJson('/api/v1/push/status')->assertOk()->assertJsonPath('data.configured', false);
    $this->postJson('/api/v1/push/test')->assertStatus(422);
    $this->deleteJson('/api/v1/push/token', ['token' => 'fcm-token-123'])->assertOk();
    expect(PushToken::count())->toBe(0);
});
