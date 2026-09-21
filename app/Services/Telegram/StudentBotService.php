<?php

namespace App\Services\Telegram;

use App\Models\Student;
use App\Models\TelegramSession;
use App\Models\TestAssignment;
use App\Models\TestAttempt;
use App\Models\TestQuestion;
use App\Models\User;
use App\Services\LearningService;
use App\Services\TestService;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * O'quvchilar Telegram boti — FAQAT variantli testlarni yechish.
 * Oqim: /start → telefon → parol → /tests → test tanlash → savollar (inline tugmalar) → natija.
 * Barcha javoblar bazadagi oddiy urinish (TestAttempt, source = telegram) sifatida saqlanadi,
 * limitlar (mock = 1 marta) platformadagi bilan bir xil.
 */
class StudentBotService
{
    public function __construct(
        private readonly TestService $tests,
        private readonly LearningService $learning,
    ) {}

    public function handle(array $update): void
    {
        try {
            if (isset($update['callback_query'])) {
                $this->handleCallback($update['callback_query']);
            } elseif (isset($update['message'])) {
                $this->handleMessage($update['message']);
            }
        } catch (Throwable $e) {
            report($e);

            $chatId = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;

            if ($chatId) {
                $this->client()->sendMessage($chatId, '⚠️ Xatolik yuz berdi: '.e($e->getMessage()));
            }
        }
    }

    private function handleMessage(array $message): void
    {
        $chatId = (string) $message['chat']['id'];
        $text = trim((string) ($message['text'] ?? ''));
        $contact = $message['contact']['phone_number'] ?? null;
        $session = $this->session($chatId);

        if ($text === '/start' || $text === '/login') {
            $session->setState(TelegramSession::STATE_AWAIT_PHONE);
            $this->reply($chatId, "👋 <b>Italiya Akademiyasi</b> botiga xush kelibsiz!\n\nKirish uchun telefon raqamingizni yuboring (masalan, +998901234567) yoki tugmani bosing.", [
                'keyboard' => [[['text' => '📱 Raqamni yuborish', 'request_contact' => true]]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]);

            return;
        }

        if ($text === '/logout') {
            $session->update(['student_id' => null, 'user_id' => null, 'state' => TelegramSession::STATE_IDLE, 'context' => null]);
            $this->reply($chatId, 'Tizimdan chiqdingiz. Qayta kirish: /start');

            return;
        }

        if ($session->state === TelegramSession::STATE_AWAIT_PHONE) {
            $phone = $contact ?: $text;
            $normalized = $this->normalizePhone($phone);
            $user = User::where('phone', $normalized)->orWhere('phone', $phone)->orWhere('username', $text)->first();
            $student = $user ? Student::where('user_id', $user->id)->first() : null;

            if (! $student) {
                $this->reply($chatId, "❌ Bu raqam bo'yicha o'quvchi topilmadi. Qayta urinib ko'ring yoki administratorga murojaat qiling.");

                return;
            }

            $session->update(['phone' => $normalized]);
            $session->setState(TelegramSession::STATE_AWAIT_PASSWORD, ['user_id' => $user->id, 'student_id' => $student->id]);
            $this->reply($chatId, '🔐 Endi parolingizni yuboring:', ['remove_keyboard' => true]);

            return;
        }

        if ($session->state === TelegramSession::STATE_AWAIT_PASSWORD) {
            $user = User::find($session->context['user_id'] ?? 0);

            if (! $user || ! Hash::check($text, $user->password)) {
                $this->reply($chatId, "❌ Parol noto'g'ri. Qayta yuboring yoki /start.");

                return;
            }

            $student = Student::find($session->context['student_id']);
            $student->update(['telegram_chat_id' => $chatId]);
            $session->update(['user_id' => $user->id, 'student_id' => $student->id, 'state' => TelegramSession::STATE_IDLE, 'context' => null]);

            $this->reply($chatId, "✅ Xush kelibsiz, <b>{$student->firstname}</b>! Daraja: <b>{$student->effectiveLevel()}</b>, guruh: {$student->schoolClass?->name}.\n\nBuyruqlar:\n/tests — testlar ro'yxati\n/results — natijalarim\n/level — daraja va ko'nikmalar\n/logout — chiqish");

            return;
        }

        $student = $this->requireStudent($session, $chatId);

        if (! $student) {
            return;
        }

        match (true) {
            $text === '/tests' => $this->listTests($chatId, $student),
            $text === '/results' => $this->results($chatId, $student),
            $text === '/level' => $this->level($chatId, $student),
            $text === '/help' => $this->reply($chatId, "/tests — testlar\n/results — natijalar\n/level — daraja\n/logout — chiqish"),
            default => $this->reply($chatId, 'Buyruqni tanlang: /tests, /results, /level'),
        };
    }

    private function handleCallback(array $callback): void
    {
        $chatId = (string) $callback['message']['chat']['id'];
        $messageId = (int) $callback['message']['message_id'];
        $data = (string) ($callback['data'] ?? '');
        $session = $this->session($chatId);
        $client = $this->client();

        $client->answerCallbackQuery($callback['id']);

        $student = $this->requireStudent($session, $chatId);

        if (! $student) {
            return;
        }

        [$action, $rest] = array_pad(explode(':', $data, 2), 2, '');

        match ($action) {
            'start' => $this->startTest($chatId, $student, (int) $rest, $session),
            'ans' => $this->answer($chatId, $messageId, $student, $session, $rest),
            'submit' => $this->submit($chatId, $student, $session, (int) $rest),
            'next' => $this->sendQuestion($chatId, $student, $session),
            default => null,
        };
    }

    /* ---------------- Testlar ---------------- */

    private function listTests(string $chatId, Student $student): void
    {
        $items = $this->learning->assignedTests($student)
            ->filter(fn ($a) => $a['state']['can_start'])
            ->filter(fn ($a) => TestAssignment::find($a['assignment_id'])?->template?->isChoiceOnly());

        if ($items->isEmpty()) {
            $this->reply($chatId, "Hozircha botda yechish mumkin bo'lgan (faqat variantli) test yo'q. Platformada tekshiring.");

            return;
        }

        $buttons = $items->map(fn ($a) => [[
            'text' => sprintf('%s %s · %d savol%s', $this->kindIcon($a['kind']), $a['title'], $a['question_count'], $a['state']['limit'] ? " · {$a['state']['remaining']}/{$a['state']['limit']} urinish" : ''),
            'callback_data' => 'start:'.$a['assignment_id'],
        ]])->values()->all();

        $this->reply($chatId, "🧪 <b>Testlar</b> ({$student->effectiveLevel()}). Birini tanlang:", ['inline_keyboard' => $buttons]);
    }

    private function startTest(string $chatId, Student $student, int $assignmentId, TelegramSession $session): void
    {
        $assignment = TestAssignment::with('template')->findOrFail($assignmentId);

        abort_unless($assignment->template->isChoiceOnly(), 422, 'Bu testda ochiq savollar bor — uni faqat platformada yechish mumkin.');

        $attempt = $this->tests->start($assignment, $student, 'telegram');
        $answered = $attempt->answers()->whereNotNull('selected_option_ids')->pluck('test_question_id')->all();

        $session->setState(TelegramSession::STATE_IN_TEST, [
            'attempt_id' => $attempt->id,
            'index' => count($answered),
        ]);

        $limit = $assignment->template->attemptsLimit();
        $this->reply($chatId, sprintf(
            "▶️ <b>%s</b>\nSavollar: %d ta · Vaqt: %d daqiqa%s\n\nHar savolda variantni bosing.",
            e($assignment->template->title),
            count($attempt->question_order ?? []),
            $assignment->template->duration_minutes,
            $limit ? " · Urinish: {$attempt->attempt_no}/{$limit}" : '',
        ));

        $this->sendQuestion($chatId, $student, $session);
    }

    private function sendQuestion(string $chatId, Student $student, TelegramSession $session): void
    {
        $attempt = TestAttempt::find($session->context['attempt_id'] ?? 0);

        if (! $attempt || $attempt->student_id !== $student->id) {
            $this->reply($chatId, 'Faol test yo\'q. /tests');

            return;
        }

        if ($attempt->isExpired()) {
            $this->submit($chatId, $student, $session, $attempt->id);

            return;
        }

        $order = $attempt->question_order ?? [];
        $index = (int) ($session->context['index'] ?? 0);

        if ($index >= count($order)) {
            $this->reply($chatId, '✅ Barcha savollarga javob berdingiz.', ['inline_keyboard' => [[['text' => '📤 Topshirish', 'callback_data' => 'submit:'.$attempt->id]]]]);

            return;
        }

        $question = TestQuestion::with('options')->find($order[$index]);

        if (! $question) {
            $session->setState(TelegramSession::STATE_IN_TEST, ['attempt_id' => $attempt->id, 'index' => $index + 1]);
            $this->sendQuestion($chatId, $student, $session);

            return;
        }

        $lang = $student->translationLanguage();
        $translation = $question->translations[$lang] ?? null;
        $text = sprintf('<b>%d/%d.</b> %s%s', $index + 1, count($order), e($question->body), $translation ? "\n<i>".e($translation).'</i>' : '');

        if ($question->isTrueFalse()) {
            $buttons = [[
                ['text' => '✅ Vero', 'callback_data' => "ans:{$attempt->id}:{$question->id}:true"],
                ['text' => '❌ Falso', 'callback_data' => "ans:{$attempt->id}:{$question->id}:false"],
                ['text' => '❓ Non dato', 'callback_data' => "ans:{$attempt->id}:{$question->id}:ng"],
            ]];
        } else {
            $buttons = $question->options->map(fn ($o) => [[
                'text' => mb_strimwidth($o->body, 0, 60, '…'),
                'callback_data' => "ans:{$attempt->id}:{$question->id}:{$o->id}",
            ]])->values()->all();
        }

        $secondsLeft = $attempt->expires_at ? max(0, (int) now()->diffInSeconds($attempt->expires_at, false)) : null;

        if ($secondsLeft !== null) {
            $text .= "\n⏱ ".intdiv($secondsLeft, 60).' daqiqa qoldi';
        }

        $this->reply($chatId, $text, ['inline_keyboard' => $buttons]);
    }

    private function answer(string $chatId, int $messageId, Student $student, TelegramSession $session, string $rest): void
    {
        [$attemptId, $questionId, $value] = array_pad(explode(':', $rest), 3, null);
        $attempt = TestAttempt::findOrFail((int) $attemptId);

        abort_unless($attempt->student_id === $student->id, 403);

        $question = TestQuestion::findOrFail((int) $questionId);

        $payload = $question->isTrueFalse()
            ? ['answer_text' => $value]
            : ['selected_option_ids' => [(int) $value]];

        $this->tests->answer($attempt, $question->id, $payload);

        $order = $attempt->question_order ?? [];
        $index = array_search($question->id, $order, true);
        $next = $index === false ? (int) ($session->context['index'] ?? 0) + 1 : $index + 1;

        $session->setState(TelegramSession::STATE_IN_TEST, ['attempt_id' => $attempt->id, 'index' => $next]);

        $this->client()->editMessageText($chatId, $messageId, "☑️ Javob qabul qilindi ({$next}/".count($order).')');
        $this->sendQuestion($chatId, $student, $session);
    }

    private function submit(string $chatId, Student $student, TelegramSession $session, int $attemptId): void
    {
        $attempt = TestAttempt::findOrFail($attemptId);
        abort_unless($attempt->student_id === $student->id, 403);

        if (! $attempt->isFinished()) {
            $attempt = $this->tests->submit($attempt);
        }

        $session->setState(TelegramSession::STATE_IDLE);

        $template = $attempt->assignment->template;
        $passed = (float) $attempt->percent >= (float) ($template->pass_percent ?? config('academy.pass_percent'));
        $bySkill = collect($attempt->ai_summary['by_skill'] ?? [])
            ->map(fn ($s, $k) => '• '.config("academy.skills.{$k}", $k).": {$s['correct']}/{$s['total']} ({$s['percent']}%)")
            ->implode("\n");

        $this->reply($chatId, sprintf(
            "%s <b>Natija: %s%%</b> (%s/%s ball)\n%s\n\n%s\n\nBoshqa testlar: /tests",
            $passed ? '🎉' : '📉',
            $attempt->percent,
            $attempt->total_score,
            $attempt->max_score,
            $passed ? "O'tdingiz!" : "O'tish bali: ".($template->pass_percent ?? config('academy.pass_percent')).'%',
            $bySkill,
        ));
    }

    private function results(string $chatId, Student $student): void
    {
        $rows = $this->learning->results($student)->take(10);

        if ($rows->isEmpty()) {
            $this->reply($chatId, 'Hali natijalar yo\'q.');

            return;
        }

        $text = "📈 <b>Oxirgi natijalar</b>\n\n".$rows->map(fn ($r) => sprintf('%s %s — <b>%s%%</b> (%s)', $r['passed'] ? '✅' : '❌', e($r['title']), $r['percent'], $r['submitted_at']))->implode("\n");

        $this->reply($chatId, $text);
    }

    private function level(string $chatId, Student $student): void
    {
        $dashboard = $this->learning->dashboard($student);
        $skills = collect($dashboard['skills'])->map(fn ($s, $k) => "• {$s['label']}: {$s['percent']}%")->implode("\n");

        $this->reply($chatId, sprintf(
            "🎓 Daraja: <b>%s</b> (%s)\nGuruh: %s\nUmumiy: <b>%s%%</b> · Streak: %d kun · O'yin ballari: %d\n\n%s",
            $dashboard['student']['level'],
            e($dashboard['student']['level_title']),
            e((string) $dashboard['student']['group']),
            $dashboard['overall_percent'],
            $dashboard['student']['streak_days'],
            $dashboard['student']['speech_game_score'],
            $skills,
        ));
    }

    /* ---------------- Yordamchilar ---------------- */

    private function session(string $chatId): TelegramSession
    {
        return TelegramSession::firstOrCreate(['bot' => 'student', 'chat_id' => $chatId], ['state' => TelegramSession::STATE_IDLE]);
    }

    private function requireStudent(TelegramSession $session, string $chatId): ?Student
    {
        $student = $session->student_id ? Student::with('schoolClass')->find($session->student_id) : null;

        if (! $student) {
            $this->reply($chatId, 'Avval tizimga kiring: /start');

            return null;
        }

        return $student;
    }

    private function reply(string $chatId, string $text, ?array $markup = null): void
    {
        $this->client()->sendMessage($chatId, $text, $markup);
    }

    private function client(): TelegramClient
    {
        return TelegramClient::student();
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits === '' ? $phone : '+'.$digits;
    }

    private function kindIcon(?string $kind): string
    {
        return match ($kind) {
            'mock' => '🎯',
            'test' => '📝',
            'homework' => '📚',
            'placement' => '🧭',
            default => '✏️',
        };
    }
}
