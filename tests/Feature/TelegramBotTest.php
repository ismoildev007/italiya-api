<?php

use App\Models\NotificationEvent;
use App\Models\TestAttempt;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedRbac();
    config(['services.telegram.student_token' => 'stud-token', 'services.telegram.student_webhook_secret' => 's3cret', 'services.telegram.token' => 'par-token', 'services.telegram.enabled' => true]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
});

function tgMessage(string $text, string $chat = '555'): array
{
    return ['message' => ['chat' => ['id' => $chat], 'text' => $text]];
}

function tgCallback(string $data, string $chat = '555'): array
{
    return ['callback_query' => ['id' => 'cb1', 'data' => $data, 'message' => ['chat' => ['id' => $chat], 'message_id' => 7]]];
}

it('o\'quvchi botda kiradi va variantli testni yechadi', function () {
    $student = makeStudent('A1');
    $template = makeTemplate('A1', 'test');
    // Botda faqat variantli savollar — ochiq savollarni olib tashlaymiz
    $template->questions()->whereNotIn('type', ['single_choice', 'true_false'])->delete();
    $template->recalculateScore();
    $assignment = assignTo($template, $student);

    $this->postJson('/api/v1/telegram/student/wrong', tgMessage('/start'))->assertStatus(403);

    $this->postJson('/api/v1/telegram/student/s3cret', tgMessage('/start'))->assertOk();
    $this->postJson('/api/v1/telegram/student/s3cret', tgMessage($student->phone))->assertOk();
    $this->postJson('/api/v1/telegram/student/s3cret', tgMessage('parol123'))->assertOk();

    expect($student->fresh()->telegram_chat_id)->toBe('555');

    $this->postJson('/api/v1/telegram/student/s3cret', tgMessage('/tests'))->assertOk();
    $this->postJson('/api/v1/telegram/student/s3cret', tgCallback("start:{$assignment->id}"))->assertOk();

    $attempt = TestAttempt::first();
    expect($attempt->source)->toBe('telegram');

    $choice = $template->questions()->where('type', 'single_choice')->first();
    $correct = $choice->options()->where('is_correct', true)->first();
    $tf = $template->questions()->where('type', 'true_false')->first();

    $this->postJson('/api/v1/telegram/student/s3cret', tgCallback("ans:{$attempt->id}:{$choice->id}:{$correct->id}"))->assertOk();
    $this->postJson('/api/v1/telegram/student/s3cret', tgCallback("ans:{$attempt->id}:{$tf->id}:true"))->assertOk();
    $this->postJson('/api/v1/telegram/student/s3cret', tgCallback("submit:{$attempt->id}"))->assertOk();

    expect($attempt->fresh()->status)->toBe(TestAttempt::STATUS_GRADED)
        ->and((float) $attempt->fresh()->percent)->toBe(100.0);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'stud-token/sendMessage') && str_contains($request['text'] ?? '', 'Natija'));
});

it('navbatdagi xabarlar Telegram orqali yuboriladi', function () {
    $student = makeStudent('A1', ['telegram_chat_id' => '900']);
    app(NotificationService::class)->levelChanged($student, 'A1', 'A2');

    $this->artisan('notifications:send')->assertSuccessful();

    expect(NotificationEvent::where('status', 'sent')->count())->toBe(1);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'stud-token/sendMessage') && ($r['chat_id'] ?? null) === '900');
});
