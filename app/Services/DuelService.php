<?php

namespace App\Services;

use App\Events\DuelInvited;
use App\Events\DuelUpdated;
use App\Models\Duel;
use App\Models\Student;
use App\Models\TestQuestion;
use App\Models\TestTemplate;
use App\Models\VocabCard;
use App\Models\VocabDeck;
use App\Support\Feature;
use App\Support\TextSimilarity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Duel: bir xil savollar, kim birinchi TO'G'RI javob bersa — raund uniki.
 * Raqib — boshqa o'quvchi (taklif kodi / tanlash) yoki bot (easy/medium/hard).
 * Bot javobi raund boshida rejalashtiriladi (kechikish + aniqlik) va har qanday
 * o'qishda "lazy" hisoblanadi — alohida worker kerak emas. Holat o'zgarishi
 * Reverb (WebSocket) orqali uzatiladi; frontend polling bilan ham ishlaydi.
 */
class DuelService
{
    public const QUESTIONS = 7;

    public const ROUND_SECONDS = 20;

    /** O'quvchining duellari: faol/kutilayotgan + so'nggi natijalar + takliflar */
    public function listFor(Student $student): array
    {
        $mine = Duel::with(['host', 'opponent'])
            ->where(fn ($q) => $q->where('host_student_id', $student->id)->orWhere('opponent_student_id', $student->id))
            ->orderByDesc('id')->limit(15)->get()
            ->each(fn (Duel $d) => $this->sync($d));

        $invites = Duel::with('host')->where('status', Duel::WAITING)->where('opponent_student_id', $student->id)->get();

        return [
            'active' => $mine->filter(fn ($d) => in_array($d->status, [Duel::WAITING, Duel::ACTIVE], true))->map(fn ($d) => $this->summary($d, $student))->values(),
            'history' => $mine->where('status', Duel::FINISHED)->take(10)->map(fn ($d) => $this->summary($d, $student))->values(),
            'invites' => $invites->map(fn ($d) => $this->summary($d, $student))->values(),
            'stats' => ['wins' => $student->duel_wins, 'losses' => $student->duel_losses, 'xp' => $student->xp],
            'bots' => collect(config('academy.duel_bots'))->map(fn ($b, $k) => ['key' => $k, 'title' => $b['title'], 'accuracy' => $b['accuracy']])->values(),
        ];
    }

    /** Online hisoblanadigan oyna (heartbeat `learn/presence` har 60 s) */
    public const ONLINE_MINUTES = 3;

    /**
     * Taklif qilish mumkin bo'lgan sheriklar (o'z darajasi): avval ONLINE'lar, keyin offline'lar.
     */
    public function opponents(Student $student, ?string $search = null): Collection
    {
        $since = now()->subMinutes(self::ONLINE_MINUTES);

        return Student::active()->where('id', '!=', $student->id)
            ->whereNotNull('user_id')
            ->where('level', $student->effectiveLevel())
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('firstname', 'like', "%{$search}%")->orWhere('lastname', 'like', "%{$search}%")))
            ->orderByRaw('CASE WHEN last_activity_at >= ? THEN 0 ELSE 1 END', [$since])
            ->orderByDesc('last_activity_at')
            ->orderBy('firstname')
            ->limit(60)
            ->get(['id', 'firstname', 'lastname', 'level', 'class_id', 'photo', 'xp', 'duel_wins', 'last_activity_at'])
            ->map(fn (Student $s) => [
                'id' => $s->id,
                'name' => $s->full_name,
                'level' => $s->level,
                'xp' => $s->xp,
                'wins' => $s->duel_wins,
                'online' => $s->last_activity_at?->gte($since) ?? false,
                'last_seen' => $s->last_activity_at?->diffForHumans(),
            ]);
    }

    /** Heartbeat — o'quvchi online deb belgilanadi */
    public function touch(Student $student): array
    {
        $student->forceFill(['last_activity_at' => now()])->saveQuietly();

        $online = Student::active()->where('level', $student->effectiveLevel())->where('id', '!=', $student->id)
            ->where('last_activity_at', '>=', now()->subMinutes(self::ONLINE_MINUTES))->count();

        return ['online' => true, 'online_count' => $online, 'at' => now()->toIso8601String()];
    }

    /** Yangi duel: sherikni taklif qilish (yoki ochiq kod) */
    public function create(Student $host, ?int $opponentId = null, ?string $level = null): Duel
    {
        abort_unless(Feature::duel(), 422, 'Duel o\'yini o\'chirilgan.');
        abort_if($opponentId === $host->id, 422, 'O\'zingizni taklif qila olmaysiz.');

        $level = $level ?: ($host->effectiveLevel() ?: 'A1');
        $questions = $this->buildQuestions($level, $host->translationLanguage());
        abort_if(count($questions) < 3, 422, 'Bu daraja uchun savollar yetarli emas — o\'qituvchi lug\'at/test qo\'shishi kerak.');

        $duel = Duel::create([
            'school_id' => config('school.id'),
            'level' => $level,
            'host_student_id' => $host->id,
            'opponent_student_id' => $opponentId,
            'invite_code' => strtoupper(Str::random(6)),
            'status' => Duel::WAITING,
            'questions' => $questions,
            'rounds' => [],
            'round_seconds' => self::ROUND_SECONDS,
        ]);

        if ($opponentId) {
            $this->broadcast(fn () => DuelInvited::dispatch($duel, $opponentId));

            $opponent = Student::find($opponentId);
            if ($opponent) {
                try {
                    app(FirebasePushService::class)->sendToStudent($opponent, 'Duel taklifi ⚔️', "{$host->full_name} sizni duelga chaqirmoqda ({$level}). Kod: {$duel->invite_code}", ['type' => 'duel_invite', 'duel_id' => $duel->id], '/learn/duel/'.$duel->id);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $duel;
    }

    /** Sherik topilmadi — bot bilan boshlash */
    public function playWithBot(Duel $duel, Student $student, string $botLevel): Duel
    {
        abort_unless($duel->host_student_id === $student->id, 403);
        abort_unless($duel->status === Duel::WAITING, 422, 'Duel allaqachon boshlangan.');
        abort_unless(config("academy.duel_bots.{$botLevel}"), 422, 'Noma\'lum bot darajasi.');

        $duel->forceFill(['opponent_student_id' => null, 'bot_level' => $botLevel])->save();

        return $this->begin($duel);
    }

    /** Taklifni qabul qilish (id yoki kod orqali) */
    public function join(Duel $duel, Student $student): Duel
    {
        abort_unless($duel->status === Duel::WAITING, 422, 'Duel kutish holatida emas.');
        abort_if($duel->host_student_id === $student->id, 422, 'Siz bu duelning egasisiz.');
        abort_if($duel->opponent_student_id && $duel->opponent_student_id !== $student->id, 403, 'Bu duel boshqa o\'quvchiga taklif qilingan.');

        $duel->forceFill(['opponent_student_id' => $student->id, 'bot_level' => null])->save();

        return $this->begin($duel);
    }

    public function findByCode(string $code): Duel
    {
        return Duel::where('invite_code', strtoupper(trim($code)))->firstOrFail();
    }

    public function cancel(Duel $duel, Student $student): Duel
    {
        abort_unless($duel->isParticipant($student), 403);

        if ($duel->status !== Duel::FINISHED) {
            $duel->update(['status' => Duel::CANCELED, 'finished_at' => now()]);
            $this->broadcast(fn () => DuelUpdated::dispatch($duel, 'canceled'));
        }

        return $duel;
    }

    /**
     * Javob: birinchi to'g'ri javob raundni yutadi; noto'g'ri javob bergan raundda qulflanadi.
     */
    public function answer(Duel $duel, Student $student, int $no, string $given): array
    {
        $side = $duel->sideOf($student);
        abort_unless($side, 403);

        return DB::transaction(function () use ($duel, $side, $no, $given) {
            $duel = Duel::lockForUpdate()->find($duel->id);
            $this->sync($duel, false);

            abort_unless($duel->status === Duel::ACTIVE, 422, 'Duel faol emas.');
            abort_unless($no === (int) $duel->current_no, 422, 'Bu raund allaqachon o\'tgan.');

            $rounds = $duel->rounds ?? [];
            $round = $rounds[(string) $no] ?? $this->newRound();
            abort_if(isset($round[$side]), 422, 'Siz bu raundda javob berdingiz.');

            $question = collect($duel->questions)->firstWhere('no', $no);
            $correct = TextSimilarity::normalize($given) === TextSimilarity::normalize((string) $question['answer']);
            $elapsed = $duel->round_started_at ? $duel->round_started_at->diffInMilliseconds(now()) / 1000 : 0;

            $round[$side] = ['answer' => $given, 'correct' => $correct, 'at' => round($elapsed, 2)];

            if ($correct && empty($round['winner'])) {
                // Bot bundan oldin to'g'ri javob bergan bo'lsa — bot yutgan (sync allaqachon belgilagan)
                $round['winner'] = $side;
            }

            $rounds[(string) $no] = $round;
            $duel->rounds = $rounds;
            $duel->save();

            $this->advanceIfDone($duel);
            $this->broadcast(fn () => DuelUpdated::dispatch($duel, 'answered'));

            return ['correct' => $correct, 'won_round' => ($round['winner'] ?? null) === $side, 'expected' => $question['answer'], 'state' => $this->state($duel->fresh(), $side)];
        });
    }

    /** O'yinchiga ko'rinadigan to'liq holat (to'g'ri javob faqat tugagan raundlar uchun) */
    public function state(Duel $duel, ?string $side = null, bool $sync = true): array
    {
        if ($sync) {
            $this->sync($duel);
            $duel->refresh();
        }

        $duel->loadMissing(['host', 'opponent']);
        $rounds = $duel->rounds ?? [];
        $current = collect($duel->questions)->firstWhere('no', $duel->current_no);
        $currentRound = $rounds[(string) $duel->current_no] ?? null;
        $elapsed = $duel->round_started_at ? max(0, $duel->round_started_at->diffInMilliseconds(now()) / 1000) : 0;

        return [
            'id' => $duel->id,
            'status' => $duel->status,
            'level' => $duel->level,
            'invite_code' => $duel->invite_code,
            'you' => $side,
            'host' => $this->player($duel, 'host'),
            'opponent' => $this->player($duel, 'opponent'),
            'total' => count($duel->questions),
            'current_no' => $duel->current_no,
            'round_seconds' => $duel->round_seconds,
            'seconds_left' => $duel->status === Duel::ACTIVE ? max(0, (int) ceil($duel->round_seconds - $elapsed)) : 0,
            'question' => $duel->status === Duel::ACTIVE && $current ? ['no' => $current['no'], 'body' => $current['body'], 'options' => $current['options'], 'hint' => $current['hint'] ?? null] : null,
            'you_answered' => $side && isset($currentRound[$side]),
            'round' => $currentRound ? ['winner' => $currentRound['winner'] ?? null, 'host_answered' => isset($currentRound['host']), 'opponent_answered' => isset($currentRound['opponent'])] : null,
            'rounds' => collect($rounds)->map(fn ($r, $k) => ['no' => (int) $k, 'winner' => $r['winner'] ?? null, 'host' => $r['host'] ?? null, 'opponent' => $r['opponent'] ?? null, 'answer' => (int) $k < $duel->current_no || $duel->status === Duel::FINISHED ? collect($duel->questions)->firstWhere('no', (int) $k)['answer'] ?? null : null])->values(),
            'host_score' => $duel->host_score,
            'opponent_score' => $duel->opponent_score,
            'winner' => $duel->winner,
            'xp' => $duel->xp,
            'started_at' => $duel->started_at?->toIso8601String(),
            'finished_at' => $duel->finished_at?->toIso8601String(),
        ];
    }

    /* ---------------- Ichki mantiq ---------------- */

    private function begin(Duel $duel): Duel
    {
        $duel->forceFill([
            'status' => Duel::ACTIVE,
            'current_no' => 1,
            'started_at' => now(),
            'round_started_at' => now(),
            'rounds' => ['1' => $this->newRound($duel)],
        ])->save();

        $this->broadcast(fn () => DuelUpdated::dispatch($duel, 'started'));

        return $duel->fresh();
    }

    private function newRound(?Duel $duel = null): array
    {
        $round = ['winner' => null];

        if ($duel?->isBot()) {
            $bot = config("academy.duel_bots.{$duel->bot_level}");
            [$min, $max] = $bot['delay'];
            $round['bot'] = [
                'at' => round(mt_rand((int) ($min * 10), (int) ($max * 10)) / 10, 1),
                'correct' => mt_rand(1, 100) <= (int) ($bot['accuracy'] * 100),
                'resolved' => false,
            ];
        }

        return $round;
    }

    /**
     * Vaqtga bog'liq o'zgarishlar: bot javobi, raund vaqti tugashi, o'yin tugashi.
     * Har bir o'qish/javobda chaqiriladi (worker shart emas).
     */
    public function sync(Duel $duel, bool $broadcast = true): void
    {
        if ($duel->status !== Duel::ACTIVE || ! $duel->round_started_at) {
            return;
        }

        $changed = false;

        // Bir nechta raund o'tib ketgan bo'lishi mumkin (o'yinchi uzilgan) — ketma-ket hisoblaymiz
        for ($guard = 0; $guard < 50 && $duel->status === Duel::ACTIVE; $guard++) {
            $rounds = $duel->rounds ?? [];
            $no = (string) $duel->current_no;
            $round = $rounds[$no] ?? $this->newRound($duel);
            $start = $duel->round_started_at;
            $elapsed = $start->diffInMilliseconds(now()) / 1000;

            // Bot javobi vaqti keldi
            if ($duel->isBot() && ! empty($round['bot']) && ! $round['bot']['resolved'] && $elapsed >= $round['bot']['at']) {
                $question = collect($duel->questions)->firstWhere('no', (int) $no);
                $round['bot']['resolved'] = true;
                $round['opponent'] = ['answer' => $round['bot']['correct'] ? $question['answer'] : $this->wrongOption($question), 'correct' => $round['bot']['correct'], 'at' => $round['bot']['at'], 'bot' => true];

                if ($round['bot']['correct'] && empty($round['winner'])) {
                    $round['winner'] = 'opponent';
                }
                $changed = true;
            }

            $rounds[$no] = $round;
            $duel->rounds = $rounds;

            if (! empty($round['winner']) || (isset($round['host']) && isset($round['opponent']))) {
                // Raund yakunlandi — keyingisi (bot hal qilgan bo'lsa) shu paytdan boshlanadi
                $at = isset($round['bot']) && $round['bot']['resolved'] ? $start->copy()->addMilliseconds((int) ($round['bot']['at'] * 1000)) : now();
                $duel->save();
                $this->nextRound($duel, $at->lessThan(now()) ? $at : now());
                $changed = true;

                continue;
            }

            if ($elapsed >= $duel->round_seconds) {
                $duel->save();
                $this->nextRound($duel, $start->copy()->addSeconds($duel->round_seconds));
                $changed = true;

                continue;
            }

            if ($changed) {
                $duel->save();
            }

            break;
        }

        if ($changed && $broadcast) {
            $this->broadcast(fn () => DuelUpdated::dispatch($duel, 'sync'));
        }
    }

    /** Raund yakunlandimi: g'olib bor yoki ikkalasi ham javob berdi */
    private function advanceIfDone(Duel $duel): void
    {
        $round = ($duel->rounds ?? [])[(string) $duel->current_no] ?? [];
        $bothAnswered = isset($round['host']) && isset($round['opponent']);

        if (! empty($round['winner']) || $bothAnswered) {
            $this->nextRound($duel);
        }
    }

    private function nextRound(Duel $duel, ?Carbon $startAt = null): void
    {
        $rounds = $duel->rounds ?? [];
        $round = $rounds[(string) $duel->current_no] ?? [];

        if (($round['winner'] ?? null) === 'host') {
            $duel->host_score += 1;
        } elseif (($round['winner'] ?? null) === 'opponent') {
            $duel->opponent_score += 1;
        }

        if ($duel->current_no >= count($duel->questions)) {
            $this->finish($duel);

            return;
        }

        $duel->current_no += 1;
        $duel->round_started_at = $startAt ?? now();
        $rounds[(string) $duel->current_no] = $this->newRound($duel);
        $duel->rounds = $rounds;
        $duel->save();
    }

    private function finish(Duel $duel): void
    {
        $winner = $duel->host_score === $duel->opponent_score ? 'draw' : ($duel->host_score > $duel->opponent_score ? 'host' : 'opponent');
        $xpCfg = config('academy.xp');
        $xp = ['host' => 0, 'opponent' => 0];

        foreach (['host', 'opponent'] as $side) {
            $score = $side === 'host' ? $duel->host_score : $duel->opponent_score;
            $xp[$side] = $score * (int) $xpCfg['duel_round'] + ($winner === $side ? (int) $xpCfg['duel_win'] : ($winner === 'draw' ? (int) $xpCfg['duel_draw'] : 0));
        }

        $duel->forceFill(['status' => Duel::FINISHED, 'winner' => $winner, 'xp' => $xp, 'finished_at' => now()])->save();

        foreach (['host' => $duel->host, 'opponent' => $duel->opponent] as $side => $student) {
            if (! $student) {
                continue;
            }
            $student->addXp($xp[$side]);
            if ($winner === $side) {
                $student->increment('duel_wins');
            } elseif ($winner !== 'draw') {
                $student->increment('duel_losses');
            }
            app(SkillProgressService::class)->record($student, $duel->level, 'vocabulary', $duel->questions ? round(($side === 'host' ? $duel->host_score : $duel->opponent_score) / count($duel->questions) * 100) : 0, $xp[$side]);
        }

        $this->broadcast(fn () => DuelUpdated::dispatch($duel, 'finished'));
    }

    private function player(Duel $duel, string $side): ?array
    {
        if ($side === 'opponent' && $duel->isBot()) {
            return ['id' => null, 'name' => config("academy.duel_bots.{$duel->bot_level}.title"), 'is_bot' => true, 'bot_level' => $duel->bot_level, 'xp' => null];
        }

        $student = $side === 'host' ? $duel->host : $duel->opponent;

        return $student ? ['id' => $student->id, 'name' => $student->full_name, 'is_bot' => false, 'xp' => $student->xp, 'level' => $student->level] : null;
    }

    private function summary(Duel $duel, Student $me): array
    {
        $side = $duel->sideOf($me);

        return [
            'id' => $duel->id,
            'status' => $duel->status,
            'level' => $duel->level,
            'invite_code' => $duel->invite_code,
            'you' => $side,
            'host' => $this->player($duel, 'host'),
            'opponent' => $this->player($duel, 'opponent'),
            'host_score' => $duel->host_score,
            'opponent_score' => $duel->opponent_score,
            'winner' => $duel->winner,
            'you_won' => $duel->winner && $duel->winner === $side,
            'xp' => $side ? ($duel->xp[$side] ?? 0) : 0,
            'created_at' => $duel->created_at?->toDateTimeString(),
            'finished_at' => $duel->finished_at?->toDateTimeString(),
        ];
    }

    /** Savollar: lug'at (tarjimani tanlash) + mashq testlaridagi variantli savollar */
    private function buildQuestions(string $level, string $lang): array
    {
        $deckIds = VocabDeck::where('is_published', true)->where('level', $level)->pluck('id');
        $cards = VocabCard::whereIn('vocab_deck_id', $deckIds)->get();
        $questions = [];

        foreach ($cards->shuffle()->take(5) as $card) {
            $others = $cards->where('id', '!=', $card->id)->shuffle()->take(3)->map(fn ($c) => $c->translation($lang));
            if ($others->count() < 3) {
                continue;
            }
            $questions[] = ['body' => "«{$card->word}» — tarjimasi?", 'options' => $others->push($card->translation($lang))->shuffle()->values()->all(), 'answer' => $card->translation($lang)];
        }

        $templateIds = TestTemplate::where('is_published', true)->where('level', $level)->pluck('id');
        $choice = TestQuestion::with('options')->whereIn('test_template_id', $templateIds)->where('type', TestQuestion::TYPE_SINGLE)->inRandomOrder()->limit(4)->get();

        foreach ($choice as $q) {
            $answer = $q->options->firstWhere('is_correct', true)?->body;
            if ($answer && $q->options->count() >= 2) {
                $questions[] = ['body' => $q->body, 'options' => $q->options->pluck('body')->shuffle()->values()->all(), 'answer' => $answer];
            }
        }

        shuffle($questions);

        return collect(array_slice($questions, 0, self::QUESTIONS))->map(fn ($q, $i) => $q + ['no' => $i + 1])->values()->all();
    }

    private function wrongOption(array $question): string
    {
        $wrong = array_values(array_filter($question['options'], fn ($o) => $o !== $question['answer']));

        return $wrong[array_rand($wrong)] ?? '';
    }

    /** WebSocket serveri ishlamayotgan bo'lsa ham API buzilmaydi */
    private function broadcast(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
