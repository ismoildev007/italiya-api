<?php

namespace App\Services;

use App\Models\CourseUnit;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\TestQuestion;
use App\Models\TestTemplate;
use App\Models\UnitProgress;
use App\Models\VocabCard;
use App\Models\VocabDeck;
use App\Support\Feature;
use App\Support\Level;
use App\Support\TextSimilarity;
use Illuminate\Support\Collection;

/**
 * "Dars yo'li" — Duolingo uslubidagi qisqa darslar: har daraja × ko'nikma uchun
 * mashqlar (juftlash, variant tanlash, yozish, matn savollari) darsliklar, lug'at
 * kartochkalari va mashq testlaridan AVTOMATIK tuziladi. Har bir tugatilgan dars
 * ko'nikma progressini +20% oshiradi va XP beradi.
 */
class LessonPathService
{
    public const STEP_PERCENT = 20;

    public function __construct(private readonly SkillProgressService $skills) {}

    /** Butun yo'l: darajalar × ko'nikmalar, progress, qulf holati, XP, streak. */
    public function map(Student $student): array
    {
        $current = $student->effectiveLevel() ?: 'A1';
        $skillKeys = Feature::skills();
        $levels = [];

        foreach (Level::all() as $level) {
            $unlocked = Level::ordinal($level) <= Level::ordinal($current);
            $progress = $this->skills->forLevel($student, $level);
            $lessons = LessonSession::where('student_id', $student->id)->where('level', $level)->whereNotNull('finished_at')
                ->selectRaw('skill, COUNT(*) as c')->groupBy('skill')->pluck('c', 'skill');

            $levels[] = [
                'level' => $level,
                'title' => Level::title($level),
                'description' => config("academy.levels.{$level}.description"),
                'unlocked' => $unlocked,
                'is_current' => $level === $current,
                'skills' => collect($skillKeys)->map(fn ($skill) => [
                    'skill' => $skill,
                    'label' => config("academy.skills.{$skill}"),
                    'percent' => (float) ($progress[$skill]['percent'] ?? 0),
                    'lessons_done' => (int) ($lessons[$skill] ?? 0),
                    'done' => (float) ($progress[$skill]['percent'] ?? 0) >= 100,
                ])->values()->all(),
            ];
        }

        return [
            'current_level' => $current,
            'xp' => (int) $student->xp,
            'streak_days' => (int) $student->streak_days,
            'duel_wins' => (int) $student->duel_wins,
            'levels' => $levels,
        ];
    }

    /** Dars haqida qisqa ma'lumot (intro ekrani) */
    public function intro(Student $student, string $level, string $skill): array
    {
        $this->assertAllowed($student, $level, $skill);
        $count = $this->exerciseCount($level, $skill);

        return [
            'level' => $level,
            'skill' => $skill,
            'title' => config("academy.skills.{$skill}").' · '.Level::title($level),
            'difficulty' => match ($level) {
                'A1', 'A2' => 'easy', 'B1', 'B2' => 'medium', default => 'hard'
            },
            'exercise_count' => $count,
            'minutes' => max(3, (int) ceil($count * 0.8)),
            'xp' => $this->xpFor($level, $count),
            'unit' => $this->unitFor($level, $skill)?->only(['id', 'title', 'description']),
        ];
    }

    /** Darsni boshlash — mashqlar tuziladi va sessiya saqlanadi. */
    public function start(Student $student, string $level, string $skill): LessonSession
    {
        $this->assertAllowed($student, $level, $skill);

        $exercises = $this->buildExercises($student, $level, $skill);
        abort_if($exercises === [], 422, 'Bu daraja va ko\'nikma uchun hali mashqlar yo\'q — o\'qituvchi darslik yoki lug\'at qo\'shishi kerak.');

        return LessonSession::create([
            'school_id' => config('school.id'),
            'student_id' => $student->id,
            'level' => $level,
            'skill' => $skill,
            'course_unit_id' => $this->unitFor($level, $skill)?->id,
            'exercises' => $exercises,
            'answers' => [],
            'total' => count($exercises),
            'started_at' => now(),
        ]);
    }

    /** O'quvchiga ko'rinadigan sessiya (to'g'ri javoblar yashirin, javob berilganlari ochiq). */
    public function view(LessonSession $session): array
    {
        $answers = $session->answers ?? [];

        return [
            'id' => $session->id,
            'level' => $session->level,
            'skill' => $session->skill,
            'skill_label' => config("academy.skills.{$session->skill}"),
            'total' => $session->total,
            'correct' => $session->correct,
            'xp_earned' => $session->xp_earned,
            'finished' => $session->isFinished(),
            'exercises' => collect($session->exercises)->map(function ($e) use ($answers) {
                $answered = $answers[(string) $e['no']] ?? null;
                $public = collect($e)->except(['answer', 'pairs_answer'])->all();

                if ($e['type'] === 'match') {
                    $public['left'] = array_map(fn ($p) => ['id' => $p['id'], 'text' => $p['left']], $e['pairs']);
                    $public['right'] = collect($e['pairs'])->map(fn ($p) => ['id' => $p['id'], 'text' => $p['right']])->shuffle()->values()->all();
                    unset($public['pairs']);
                }

                $public['result'] = $answered ? ['correct' => $answered['correct'], 'given' => $answered['given'], 'expected' => $e['answer'] ?? null] : null;

                return $public;
            })->values()->all(),
        ];
    }

    /**
     * Mashqqa javob: match → {pairs: {leftId: rightId}}, boshqalar → {answer}.
     *
     * @return array{correct: bool, expected: mixed, xp: int}
     */
    public function answer(LessonSession $session, int $no, mixed $given): array
    {
        abort_if($session->isFinished(), 422, 'Dars yakunlangan.');

        $exercise = collect($session->exercises)->firstWhere('no', $no);
        abort_unless($exercise, 404, 'Mashq topilmadi.');

        $answers = $session->answers ?? [];
        abort_if(isset($answers[(string) $no]), 422, 'Bu mashqqa javob berilgan.');

        $correct = match ($exercise['type']) {
            'match' => $this->checkMatch($exercise, is_array($given) ? $given : []),
            'typing', 'gap' => TextSimilarity::ratio((string) $given, (string) $exercise['answer']) >= 0.85,
            default => TextSimilarity::normalize((string) $given) === TextSimilarity::normalize((string) $exercise['answer']),
        };

        $xp = $correct ? (int) config('academy.xp.exercise_correct', 2) : 0;
        $answers[(string) $no] = ['given' => $given, 'correct' => $correct, 'at' => now()->toIso8601String()];

        $session->update([
            'answers' => $answers,
            'correct' => $session->correct + ($correct ? 1 : 0),
            'xp_earned' => $session->xp_earned + $xp,
        ]);

        return ['correct' => $correct, 'expected' => $exercise['type'] === 'match' ? null : $exercise['answer'], 'xp' => $xp, 'progress' => count($answers).'/'.$session->total];
    }

    /** Yakunlash: progress +20%, XP, darslik "tugatildi". */
    public function finish(LessonSession $session): array
    {
        if (! $session->isFinished()) {
            $student = $session->student;
            $accuracy = $session->total > 0 ? $session->correct / $session->total : 0;
            $bonus = (int) round($this->xpFor($session->level, $session->total) * $accuracy);

            $session->update(['finished_at' => now(), 'xp_earned' => $session->xp_earned + $bonus]);

            $current = (float) ($this->skills->forLevel($student, $session->level)[$session->skill]['percent'] ?? 0);
            $this->skills->record($student, $session->level, $session->skill, min(100, $current + self::STEP_PERCENT * max(0.5, $accuracy)), $session->xp_earned);
            $student->addXp($session->xp_earned);

            if ($session->course_unit_id) {
                UnitProgress::updateOrCreate(
                    ['course_unit_id' => $session->course_unit_id, 'student_id' => $student->id],
                    ['school_id' => config('school.id'), 'completed_at' => now(), 'score' => round($accuracy * 100, 2)],
                );
            }
        }

        $session->refresh();

        return [
            'finished' => true,
            'correct' => $session->correct,
            'total' => $session->total,
            'accuracy' => $session->total ? round($session->correct / $session->total * 100) : 0,
            'xp_earned' => $session->xp_earned,
            'xp_total' => (int) $session->student->fresh()->xp,
            'skill_percent' => (float) ($this->skills->forLevel($session->student, $session->level)[$session->skill]['percent'] ?? 0),
        ];
    }

    /* ---------------- Mashqlarni tuzish ---------------- */

    private function buildExercises(Student $student, string $level, string $skill): array
    {
        $lang = $student->translationLanguage();
        $cards = $this->cards($level, $student->effectiveTrack());
        $questions = $this->questions($level, $skill);
        $exercises = [];
        $no = 1;

        // 1) Juftlash (so'z ↔ tarjima) — barcha ko'nikmalar uchun kirish mashqi
        if ($cards->count() >= 3) {
            $picked = $cards->shuffle()->take(4);
            $exercises[] = [
                'no' => $no++, 'type' => 'match', 'prompt' => 'Har bir so\'zga mos tarjimani toping.',
                'pairs' => $picked->map(fn (VocabCard $c) => ['id' => $c->id, 'left' => $c->word, 'right' => $c->translation($lang)])->values()->all(),
            ];
        }

        // 2) Ko'nikmaga xos savollar (mashq testidan)
        foreach ($questions->shuffle()->take(4) as $q) {
            if ($q->isChoice()) {
                $exercises[] = ['no' => $no++, 'type' => 'choice', 'prompt' => $q->body, 'passage' => $q->section?->passage, 'options' => $q->options->pluck('body')->shuffle()->values()->all(), 'answer' => $q->options->firstWhere('is_correct', true)?->body];
            } elseif ($q->isTrueFalse()) {
                $exercises[] = ['no' => $no++, 'type' => 'choice', 'prompt' => $q->body, 'passage' => $q->section?->passage, 'options' => ['true', 'false', 'ng'], 'answer' => $q->correct_answer, 'labels' => ['true' => 'Vero', 'false' => 'Falso', 'ng' => 'Non dato']];
            } elseif ($q->isSimilarity()) {
                $exercises[] = ['no' => $no++, 'type' => 'gap', 'prompt' => $q->body, 'answer' => $q->correct_answer];
            }
        }

        // 3) Lug'at: tarjimani tanlash va so'zni yozish
        foreach ($cards->shuffle()->take(3) as $i => $card) {
            $others = $cards->where('id', '!=', $card->id)->shuffle()->take(3)->map(fn ($c) => $c->translation($lang));
            if ($i < 2) {
                $exercises[] = ['no' => $no++, 'type' => 'choice', 'prompt' => "«{$card->word}» so'zining ma'nosi?", 'options' => $others->push($card->translation($lang))->shuffle()->values()->all(), 'answer' => $card->translation($lang), 'hint' => $card->example];
            } else {
                $exercises[] = ['no' => $no++, 'type' => 'typing', 'prompt' => "Italyanchaga tarjima qiling: «{$card->translation($lang)}»", 'answer' => $card->word, 'hint' => $card->example];
            }
        }

        // Speaking: namunaviy javob bilan gapirish (o'qituvchi tekshirmaydi — o'z-o'zini baholash)
        if ($skill === 'speaking') {
            foreach ($questions->where('type', TestQuestion::TYPE_SPEAKING)->shuffle()->take(2) as $q) {
                $exercises[] = ['no' => $no++, 'type' => 'speak', 'prompt' => $q->body, 'model_answer' => $q->model_answer, 'answer' => 'ok'];
            }
        }

        return $exercises;
    }

    private function exerciseCount(string $level, string $skill): int
    {
        $cards = $this->cards($level, 'general')->count();
        $questions = $this->questions($level, $skill)->count();

        return ($cards >= 3 ? 1 : 0) + min(4, $questions) + min(3, $cards) + ($skill === 'speaking' ? 2 : 0);
    }

    private function cards(string $level, string $track): Collection
    {
        $deckIds = VocabDeck::where('is_published', true)->where('level', $level)->whereIn('track', array_unique([$track, 'general']))->pluck('id');

        return VocabCard::whereIn('vocab_deck_id', $deckIds)->get();
    }

    private function questions(string $level, string $skill): Collection
    {
        $templateIds = TestTemplate::where('is_published', true)->where('level', $level)->whereIn('kind', [TestTemplate::KIND_PRACTICE, TestTemplate::KIND_HOMEWORK])->pluck('id');

        return TestQuestion::with(['options', 'section'])
            ->whereIn('test_template_id', $templateIds)
            ->where(fn ($q) => $q->where('skill', $skill)->orWhereHas('section', fn ($s) => $s->where('skill', $skill)))
            ->whereIn('type', [TestQuestion::TYPE_SINGLE, TestQuestion::TYPE_TRUE_FALSE, TestQuestion::TYPE_GAP, TestQuestion::TYPE_SHORT_TEXT, TestQuestion::TYPE_SPEAKING])
            ->get();
    }

    private function unitFor(string $level, string $skill): ?CourseUnit
    {
        return CourseUnit::published()->available()->where('level', $level)->where('skill', $skill)->orderBy('order')->first();
    }

    private function checkMatch(array $exercise, array $given): bool
    {
        foreach ($exercise['pairs'] as $pair) {
            if ((int) ($given[(string) $pair['id']] ?? $given[$pair['id']] ?? 0) !== (int) $pair['id']) {
                return false;
            }
        }

        return true;
    }

    private function xpFor(string $level, int $count): int
    {
        return (int) config('academy.xp.unit_complete', 10) + Level::ordinal($level) * 5 + $count;
    }

    private function assertAllowed(Student $student, string $level, string $skill): void
    {
        abort_unless(in_array($skill, Feature::skills(), true), 422, 'Bu ko\'nikma o\'chirilgan.');
        abort_if(Level::ordinal($level) > Level::ordinal($student->effectiveLevel() ?: 'A1'), 403, 'Bu daraja hali ochilmagan.');
    }
}
