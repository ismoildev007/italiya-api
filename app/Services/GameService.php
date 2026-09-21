<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameSession;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\VocabCard;
use App\Models\VocabDeck;
use App\Support\TextSimilarity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * So'zlashuv o'yini (mockup): raundlar it→tarjima, tarjima→it, ovozli talaffuz,
 * yozish, eshitish. Har to'g'ri javob +N ball, xato — raund keyinroq qaytadi.
 * O'qituvchi har bir daraja uchun o'yin yaratadi va savollar sonini belgilaydi.
 */
class GameService
{
    public function __construct(
        private readonly VoiceTranscriptionService $transcription,
        private readonly SkillProgressService $skills,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Game::query()
            ->with(['deck', 'teacher.user'])
            ->withCount('sessions')
            ->search($filters['search'] ?? null, ['title'])
            ->filter($filters, [
                'level' => 'level',
                'track' => 'track',
                'type' => 'type',
                'vocab_deck_id' => 'vocab_deck_id',
                'is_active' => ['is_active', 'bool'],
            ])
            ->orderBy('level')->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function save(array $data, ?Game $game = null): Game
    {
        $payload = [
            'school_id' => config('school.id'),
            'teacher_id' => $data['teacher_id'] ?? $game?->teacher_id ?? Teacher::where('user_id', auth()->id())->value('id'),
            'vocab_deck_id' => $data['vocab_deck_id'] ?? $game?->vocab_deck_id,
            'title' => $data['title'] ?? $game?->title,
            'type' => $data['type'] ?? $game?->type ?? 'flashcard',
            'level' => $data['level'] ?? $game?->level,
            'track' => $data['track'] ?? $game?->track ?? 'general',
            'card_count' => $data['card_count'] ?? $game?->card_count ?? config('academy.games.default_card_count'),
            'seconds_per_card' => $data['seconds_per_card'] ?? $game?->seconds_per_card ?? config('academy.games.default_seconds_per_card'),
            'round_types' => $data['round_types'] ?? $game?->round_types,
            'prompt_language' => $data['prompt_language'] ?? $game?->prompt_language ?? 'it',
            'answer_language' => $data['answer_language'] ?? $game?->answer_language,
            'points_per_correct' => $data['points_per_correct'] ?? $game?->points_per_correct ?? 10,
            'is_active' => $data['is_active'] ?? $game?->is_active ?? true,
            'created_by' => $game?->created_by ?? auth()->id(),
        ];

        return ($game ? tap($game)->update($payload) : Game::create($payload))->fresh(['deck', 'teacher.user']);
    }

    public function delete(Game $game): void
    {
        $game->delete();
    }

    /** O'quvchi darajasiga mos faol o'yinlar. */
    public function forStudent(Student $student, ?string $level = null): Collection
    {
        $level = $level ?? $student->effectiveLevel();

        return Game::where('is_active', true)
            ->where('level', $level)
            ->whereIn('track', array_unique([$student->effectiveTrack(), 'general']))
            ->with('deck')
            ->withCount(['sessions as my_sessions_count' => fn ($q) => $q->where('student_id', $student->id)])
            ->orderBy('id')
            ->get()
            ->each(fn (Game $game) => $game->setAttribute('my_best_score', (int) GameSession::where('game_id', $game->id)->where('student_id', $student->id)->max('score')));
    }

    /**
     * O'yinni boshlaydi: `card_count` ta kartadan raundlar tuziladi (tasodifiy, darajaga mos).
     */
    public function start(Game $game, Student $student, string $source = 'web'): GameSession
    {
        abort_unless($game->is_active, 422, 'O\'yin faol emas.');

        $lang = $game->answer_language ?: $student->translationLanguage();
        $cards = $this->cardPool($game);

        abort_if($cards->count() < 3, 422, 'O\'yin uchun kamida 3 ta kartochka kerak.');

        $picked = $cards->shuffle()->take($game->card_count)->values();
        $types = $game->roundTypes();
        $rounds = [];

        foreach ($picked as $index => $card) {
            $type = $types[$index % count($types)];
            $rounds[] = $this->buildRound($type, $card, $cards, $lang, $index + 1);
        }

        shuffle($rounds);

        foreach ($rounds as $i => &$round) {
            $round['no'] = $i + 1;
        }

        return GameSession::create([
            'school_id' => config('school.id'),
            'game_id' => $game->id,
            'student_id' => $student->id,
            'started_at' => now(),
            'total_rounds' => count($rounds),
            'rounds' => $rounds,
            'details' => [],
            'source' => $source,
        ]);
    }

    /**
     * Bitta raund javobi. Ovozli raundda audio yuklanadi (STT) yoki mijoz transkripti keladi.
     *
     * @return array{correct: bool, expected: string, given: ?string, similarity: ?float, score: int, points: int}
     */
    public function answer(GameSession $session, int $roundNo, array $data, ?UploadedFile $audio = null): array
    {
        abort_if($session->isFinished(), 422, 'O\'yin yakunlangan.');

        $rounds = collect($session->rounds);
        $round = $rounds->firstWhere('no', $roundNo);
        abort_unless($round, 404, 'Raund topilmadi.');

        $details = $session->details ?? [];
        abort_if(collect($details)->contains(fn ($d) => $d['no'] === $roundNo && $d['correct']), 422, 'Bu raund allaqachon to\'g\'ri yechilgan.');

        $given = $data['answer'] ?? null;
        $similarity = null;

        if ($audio) {
            $path = $audio->store('games/audio', 'public');
            [$given] = $this->transcription->transcribePath($path, $round['answer_language'] ?? 'it');
        }

        $expected = (string) $round['answer'];

        if ($round['type'] === Game::ROUND_VOICE || $round['type'] === Game::ROUND_TYPING || $round['type'] === Game::ROUND_LISTENING) {
            $similarity = TextSimilarity::ratio((string) $given, $expected);
            $correct = $given !== null && $similarity >= ($round['type'] === Game::ROUND_VOICE ? 0.8 : 0.9);
        } else {
            $correct = $given !== null && TextSimilarity::normalize((string) $given) === TextSimilarity::normalize($expected);
        }

        $points = $correct ? (int) $session->game->points_per_correct : 0;

        $details[] = [
            'no' => $roundNo,
            'type' => $round['type'],
            'given' => $given,
            'expected' => $expected,
            'correct' => $correct,
            'similarity' => $similarity,
            'answered_at' => now()->toIso8601String(),
        ];

        $session->update([
            'details' => $details,
            'correct' => $correct ? $session->correct + 1 : $session->correct,
            'wrong' => $correct ? $session->wrong : $session->wrong + 1,
            'score' => $session->score + $points,
        ]);

        return [
            'correct' => $correct,
            'expected' => $expected,
            'given' => $given,
            'similarity' => $similarity,
            'points' => $points,
            'score' => $session->fresh()->score,
            'remaining' => $session->total_rounds - collect($details)->where('correct', true)->pluck('no')->unique()->count(),
        ];
    }

    /** O'yinni yakunlaydi: ball o'quvchining `speech_game_score` ga qo'shiladi, lug'at progressi yangilanadi. */
    public function finish(GameSession $session): GameSession
    {
        if ($session->isFinished()) {
            return $session;
        }

        return DB::transaction(function () use ($session) {
            $session->update([
                'finished_at' => now(),
                'duration_seconds' => (int) $session->started_at->diffInSeconds(now()),
            ]);

            $student = $session->student;
            $student->increment('speech_game_score', $session->score);

            $percent = $session->total_rounds > 0 ? round($session->correct / $session->total_rounds * 100, 2) : 0;
            $this->skills->record($student, $session->game->level, 'vocabulary', $percent, $session->score);

            if (in_array(Game::ROUND_VOICE, $session->game->roundTypes(), true)) {
                $voice = collect($session->details)->where('type', Game::ROUND_VOICE);

                if ($voice->isNotEmpty()) {
                    $this->skills->record($student, $session->game->level, 'speaking', round($voice->where('correct', true)->count() / $voice->count() * 100, 2), 0);
                }
            }

            return $session->fresh(['game', 'student']);
        });
    }

    /**
     * Liderbord: o'yin bo'yicha yoki umumiy (speech_game_score), daraja/guruh filtri bilan.
     */
    public function leaderboard(?Game $game = null, array $filters = [], int $limit = 20): Collection
    {
        if ($game) {
            return GameSession::query()
                ->selectRaw('student_id, MAX(score) as best_score, SUM(score) as total_score, COUNT(*) as sessions')
                ->where('game_id', $game->id)
                ->whereNotNull('finished_at')
                ->groupBy('student_id')
                ->orderByDesc('best_score')
                ->limit($limit)
                ->with('student:id,firstname,lastname,level,class_id,photo')
                ->get()
                ->values()
                ->map(fn ($row, $i) => [
                    'rank' => $i + 1,
                    'student_id' => $row->student_id,
                    'name' => $row->student?->full_name,
                    'level' => $row->student?->level,
                    'best_score' => (int) $row->best_score,
                    'total_score' => (int) $row->total_score,
                    'sessions' => (int) $row->sessions,
                ]);
        }

        return Student::active()
            ->when($filters['level'] ?? null, fn ($q, $l) => $q->where('level', $l))
            ->when($filters['class_id'] ?? null, fn ($q, $c) => $q->where('class_id', $c))
            ->where('speech_game_score', '>', 0)
            ->orderByDesc('speech_game_score')
            ->limit($limit)
            ->get(['id', 'firstname', 'lastname', 'level', 'class_id', 'photo', 'speech_game_score'])
            ->values()
            ->map(fn (Student $s, $i) => [
                'rank' => $i + 1,
                'student_id' => $s->id,
                'name' => $s->full_name,
                'level' => $s->level,
                'score' => (int) $s->speech_game_score,
            ]);
    }

    public function results(Game $game, int $perPage = 20): LengthAwarePaginator
    {
        return GameSession::where('game_id', $game->id)
            ->with('student')
            ->orderByDesc('score')
            ->paginate($perPage);
    }

    /* ---------------- Yordamchilar ---------------- */

    /** O'yin to'plami — bo'lmasa daraja bo'yicha barcha nashr qilingan kartalar. */
    private function cardPool(Game $game): Collection
    {
        if ($game->vocab_deck_id) {
            return $game->deck->cards()->get();
        }

        $deckIds = VocabDeck::where('is_published', true)
            ->where('level', $game->level)
            ->whereIn('track', array_unique([$game->track, 'general']))
            ->pluck('id');

        return VocabCard::whereIn('vocab_deck_id', $deckIds)->get();
    }

    private function buildRound(string $type, VocabCard $card, Collection $pool, string $lang, int $index): array
    {
        $distractors = $pool->where('id', '!=', $card->id)->shuffle()->take(3);

        return match ($type) {
            Game::ROUND_TR_TO_IT => [
                'type' => $type,
                'card_id' => $card->id,
                'prompt' => $card->translation($lang),
                'prompt_language' => $lang,
                'answer' => $card->word,
                'answer_language' => 'it',
                'options' => $distractors->map->word->push($card->word)->shuffle()->values()->all(),
            ],
            Game::ROUND_VOICE => [
                'type' => $type,
                'card_id' => $card->id,
                'prompt' => $card->word,
                'hint' => $card->translation($lang),
                'prompt_language' => 'it',
                'answer' => $card->word,
                'answer_language' => 'it',
                'audio_url' => $card->audio_url,
            ],
            Game::ROUND_TYPING => [
                'type' => $type,
                'card_id' => $card->id,
                'prompt' => $card->word,
                'prompt_language' => 'it',
                'answer' => $card->translation($lang),
                'answer_language' => $lang,
            ],
            Game::ROUND_LISTENING => [
                'type' => $type,
                'card_id' => $card->id,
                'prompt' => null,
                'audio_url' => $card->audio_url,
                'tts_text' => $card->word,
                'prompt_language' => 'it',
                'answer' => $card->word,
                'answer_language' => 'it',
            ],
            default => [
                'type' => Game::ROUND_IT_TO_TR,
                'card_id' => $card->id,
                'prompt' => $card->word,
                'prompt_language' => 'it',
                'answer' => $card->translation($lang),
                'answer_language' => $lang,
                'options' => $distractors->map(fn ($c) => $c->translation($lang))->push($card->translation($lang))->shuffle()->values()->all(),
            ],
        } + ['seconds' => null];
    }
}
