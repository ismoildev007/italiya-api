<?php

namespace App\Http\Controllers;

use App\Http\Requests\Homework\SubmitHomeworkRequest;
use App\Http\Requests\Learn\AnswerRequest;
use App\Http\Requests\Learn\DuelRequest;
use App\Http\Requests\Learn\GameAnswerRequest;
use App\Http\Requests\Learn\LearnFilterRequest;
use App\Http\Requests\Learn\LessonAnswerRequest;
use App\Http\Requests\Learn\SubmitAttemptRequest;
use App\Http\Resources\CertificateResource;
use App\Http\Resources\CourseUnitResource;
use App\Http\Resources\GameResource;
use App\Http\Resources\GameSessionResource;
use App\Http\Resources\HomeworkResource;
use App\Http\Resources\HomeworkSubmissionResource;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\MonthlyReportResource;
use App\Http\Resources\VocabDeckResource;
use App\Models\CourseUnit;
use App\Models\Duel;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\Homework;
use App\Models\LessonSession;
use App\Models\Student;
use App\Models\TestAssignment;
use App\Models\TestAttempt;
use App\Models\TestQuestion;
use App\Models\TestSection;
use App\Models\TestTemplate;
use App\Models\VocabDeck;
use App\Services\CertificateService;
use App\Services\CourseUnitService;
use App\Services\DuelService;
use App\Services\GameService;
use App\Services\HomeworkService;
use App\Services\LearningService;
use App\Services\LessonPathService;
use App\Services\MediaPlayService;
use App\Services\MeetingService;
use App\Services\MonthlyReportService;
use App\Services\TestService;
use App\Services\VocabService;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O'quvchi kabineti (`/learn`). Telefon + parol bilan kirgan o'quvchi faqat
 * o'z darajasi va guruhiga mos darsliklar, testlar, o'yinlar, uy ishlarini ko'radi.
 */
class LearnController extends Controller
{
    public function __construct(
        private readonly LearningService $learning,
        private readonly TestService $tests,
        private readonly CourseUnitService $units,
        private readonly VocabService $vocab,
        private readonly GameService $games,
        private readonly HomeworkService $homework,
        private readonly MeetingService $meetings,
        private readonly CertificateService $certificates,
        private readonly MediaPlayService $plays,
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        return $this->ok($this->learning->dashboard($this->learning->student($request->user())));
    }

    /* ---------------- Darsliklar ---------------- */

    public function units(LearnFilterRequest $request): JsonResponse
    {
        $student = $this->learning->student($request->user());

        return $this->ok(CourseUnitResource::collection($this->units->forStudent($student, $request->validated())));
    }

    public function unit(Request $request, CourseUnit $unit): JsonResponse
    {
        return $this->ok(new CourseUnitResource($this->units->view($this->learning->student($request->user()), $unit)));
    }

    /** Audio/videoni tinglash — limit tugasa 423 */
    public function playUnit(Request $request, CourseUnit $unit): JsonResponse
    {
        abort_unless(Feature::audio(), 422, 'Audio funksiyasi o\'chirilgan.');

        return $this->ok($this->units->play($this->learning->student($request->user()), $unit));
    }

    public function completeUnit(Request $request, CourseUnit $unit): JsonResponse
    {
        $this->units->complete($this->learning->student($request->user()), $unit);

        return $this->ok(null, 'Dars yakunlandi.');
    }

    /* ---------------- Testlar ---------------- */

    public function tests(LearnFilterRequest $request): JsonResponse
    {
        $student = $this->learning->student($request->user());

        return $this->ok([
            'assigned' => $this->learning->assignedTests($student, $request->validated()),
            'practice' => $this->learning->practiceTests($student, $request->validated()),
        ]);
    }

    public function startAssignment(Request $request, TestAssignment $assignment): JsonResponse
    {
        $student = $this->learning->student($request->user());
        $attempt = $this->tests->start($assignment, $student);

        return $this->ok($this->learning->attemptView($attempt, $student), 'Test boshlandi.');
    }

    public function startPractice(Request $request, TestTemplate $test): JsonResponse
    {
        $student = $this->learning->student($request->user());
        $attempt = $this->learning->startPractice($test, $student);

        return $this->ok($this->learning->attemptView($attempt, $student), 'Mashq boshlandi.');
    }

    public function attempt(Request $request, TestAttempt $attempt): JsonResponse
    {
        return $this->ok($this->learning->attemptView($attempt, $this->learning->student($request->user())));
    }

    /** Bo'limni ochish — bo'lim taymeri (reading/writing) shu paytdan boshlanadi */
    public function openSection(Request $request, TestAttempt $attempt, TestSection $section): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);

        return $this->ok($this->tests->openSection($attempt, $section));
    }

    /** Bo'lim yoki savol audiosini eshitish — limit tugasa 423 */
    public function playSection(Request $request, TestAttempt $attempt, TestSection $section): JsonResponse
    {
        $student = $this->authorizeAttempt($request, $attempt);
        $this->tests->assertOpen($attempt);

        return $this->ok($this->plays->register($student, $section, $attempt));
    }

    public function playQuestion(Request $request, TestAttempt $attempt, TestQuestion $question): JsonResponse
    {
        $student = $this->authorizeAttempt($request, $attempt);
        $this->tests->assertOpen($attempt);

        return $this->ok($this->plays->register($student, $question, $attempt));
    }

    /** Bitta savolga javob (autosave); ovozli javob — `audio` fayl */
    public function answer(AnswerRequest $request, TestAttempt $attempt): JsonResponse
    {
        $this->authorizeAttempt($request, $attempt);
        $data = $request->validated();

        $answer = $this->tests->answer($attempt, (int) $data['question_id'], $data, $request->file('audio'));

        return $this->ok([
            'question_id' => $answer->test_question_id,
            'transcript' => $answer->transcript,
            'audio_url' => $answer->audio_path ? asset('storage/'.$answer->audio_path) : null,
        ], 'Javob saqlandi.');
    }

    public function submit(SubmitAttemptRequest $request, TestAttempt $attempt): JsonResponse
    {
        $student = $this->authorizeAttempt($request, $attempt);
        $attempt = $this->tests->submit($attempt, $request->validated()['answers'] ?? []);

        return $this->ok($this->learning->attemptView($attempt, $student), 'Test topshirildi.');
    }

    public function results(LearnFilterRequest $request): JsonResponse
    {
        return $this->ok($this->learning->results($this->learning->student($request->user()), $request->validated()));
    }

    /* ---------------- Lug'at va o'yinlar ---------------- */

    public function decks(LearnFilterRequest $request): JsonResponse
    {
        return $this->ok(VocabDeckResource::collection($this->vocab->forStudent($this->learning->student($request->user()), $request->validated())));
    }

    public function cards(LearnFilterRequest $request, VocabDeck $deck): JsonResponse
    {
        $student = $this->learning->student($request->user());
        $lang = $request->validated()['lang'] ?? $student->translationLanguage();

        return $this->ok([
            'deck' => new VocabDeckResource($deck),
            'language' => $lang,
            'cards' => $this->vocab->cardsFor($deck, $lang, $request->boolean('shuffle')),
        ]);
    }

    public function games(LearnFilterRequest $request): JsonResponse
    {
        $student = $this->learning->student($request->user());

        return $this->ok(GameResource::collection($this->games->forStudent($student, $request->validated()['level'] ?? null)));
    }

    public function startGame(Request $request, Game $game): JsonResponse
    {
        $session = $this->games->start($game, $this->learning->student($request->user()));

        return $this->ok($this->hideAnswers(new GameSessionResource($session->load('game'))), 'O\'yin boshlandi.');
    }

    public function answerGame(GameAnswerRequest $request, GameSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);
        $data = $request->validated();

        return $this->ok($this->games->answer($session, (int) $data['round_no'], $data, $request->file('audio')));
    }

    public function finishGame(Request $request, GameSession $session): JsonResponse
    {
        $this->authorizeSession($request, $session);

        return $this->ok(new GameSessionResource($this->games->finish($session)), 'O\'yin yakunlandi.');
    }

    public function leaderboard(Request $request, ?Game $game = null): JsonResponse
    {
        $student = $this->learning->student($request->user());

        return $this->ok($this->games->leaderboard($game, ['level' => $request->input('level', $student->effectiveLevel())], (int) $request->input('limit', 20)));
    }

    /* ---------------- Uy ishi, mitinglar, sertifikat, hisobot ---------------- */

    public function homeworks(LearnFilterRequest $request): JsonResponse
    {
        return $this->ok(HomeworkResource::collection($this->homework->forStudent($this->learning->student($request->user()), $request->validated()['status'] ?? null)));
    }

    public function submitHomework(SubmitHomeworkRequest $request, Homework $homework): JsonResponse
    {
        $submission = $this->homework->submit($homework, $this->learning->student($request->user()), $request->validated(), $request->file('file'), $request->file('audio'));

        return $this->created(new HomeworkSubmissionResource($submission), 'Uy ishi topshirildi.');
    }

    public function meetings(Request $request): JsonResponse
    {
        return $this->ok(MeetingResource::collection($this->meetings->forStudent($this->learning->student($request->user()), ! $request->boolean('all'))));
    }

    public function certificateEligibility(Request $request): JsonResponse
    {
        $result = $this->certificates->eligibility($this->learning->student($request->user()));
        $result['already_issued'] = $result['already_issued'] ? new CertificateResource($result['already_issued']) : null;

        return $this->ok($result);
    }

    /** Sertifikat generatori — oyiga 1 marta, 25-sanadan keyin */
    public function issueCertificate(Request $request): JsonResponse
    {
        $student = $this->learning->student($request->user());

        return $this->created(new CertificateResource($this->certificates->issue($student, null, $request->user()->id)->load('student')), 'Sertifikat yaratildi.');
    }

    public function certificates(Request $request): JsonResponse
    {
        $student = $this->learning->student($request->user());

        return $this->ok(CertificateResource::collection($student->certificates()->orderByDesc('issued_at')->get()));
    }

    public function monthlyReport(Request $request, MonthlyReportService $reports): JsonResponse
    {
        $student = $this->learning->student($request->user());
        $report = $reports->build($student, (int) $request->input('year', now()->year), (int) $request->input('month', now()->month), false);

        return $this->ok(new MonthlyReportResource($report));
    }

    /* ---------------- Dars yo'li ---------------- */

    public function path(Request $request, LessonPathService $path): JsonResponse
    {
        return $this->ok($path->map($this->learning->student($request->user())));
    }

    public function lessonIntro(Request $request, LessonPathService $path, string $level, string $skill): JsonResponse
    {
        return $this->ok($path->intro($this->learning->student($request->user()), strtoupper($level), $skill));
    }

    public function lessonStart(Request $request, LessonPathService $path, string $level, string $skill): JsonResponse
    {
        $session = $path->start($this->learning->student($request->user()), strtoupper($level), $skill);

        return $this->ok($path->view($session), 'Dars boshlandi.');
    }

    public function lesson(Request $request, LessonPathService $path, LessonSession $lesson): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);

        return $this->ok($path->view($lesson));
    }

    public function lessonAnswer(LessonAnswerRequest $request, LessonPathService $path, LessonSession $lesson): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);
        $data = $request->validated();

        return $this->ok($path->answer($lesson, (int) $data['no'], $data['answer'] ?? null));
    }

    public function lessonFinish(Request $request, LessonPathService $path, LessonSession $lesson): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);

        return $this->ok($path->finish($lesson), 'Dars yakunlandi!');
    }

    /* ---------------- Duel ---------------- */

    public function duels(Request $request, DuelService $duels): JsonResponse
    {
        return $this->ok($duels->listFor($this->learning->student($request->user())));
    }

    /** Heartbeat — online holati (duel sheriklari ro'yxati uchun) */
    public function presence(Request $request, DuelService $duels): JsonResponse
    {
        return $this->ok($duels->touch($this->learning->student($request->user())));
    }

    public function duelOpponents(DuelRequest $request, DuelService $duels): JsonResponse
    {
        return $this->ok($duels->opponents($this->learning->student($request->user()), $request->validated()['q'] ?? null));
    }

    public function duelCreate(DuelRequest $request, DuelService $duels): JsonResponse
    {
        $student = $this->learning->student($request->user());
        $data = $request->validated();
        $duel = $duels->create($student, isset($data['opponent_student_id']) ? (int) $data['opponent_student_id'] : null, $data['level'] ?? null);

        return $this->created($duels->state($duel, 'host'), 'Duel yaratildi.');
    }

    public function duelJoin(DuelRequest $request, DuelService $duels, ?Duel $duel = null): JsonResponse
    {
        $student = $this->learning->student($request->user());
        $duel = $duel ?? $duels->findByCode((string) ($request->validated()['code'] ?? ''));
        $duel = $duels->join($duel, $student);

        return $this->ok($duels->state($duel, 'opponent'), 'Duel boshlandi!');
    }

    public function duelBot(DuelRequest $request, DuelService $duels, Duel $duel): JsonResponse
    {
        $student = $this->learning->student($request->user());
        $duel = $duels->playWithBot($duel, $student, $request->validated()['bot_level'] ?? 'medium');

        return $this->ok($duels->state($duel, 'host'), 'Bot bilan duel boshlandi!');
    }

    public function duel(Request $request, DuelService $duels, Duel $duel): JsonResponse
    {
        $student = $this->learning->student($request->user());
        abort_unless($duel->isParticipant($student) || $duel->status === Duel::WAITING, 403);

        return $this->ok($duels->state($duel, $duel->sideOf($student)));
    }

    public function duelAnswer(DuelRequest $request, DuelService $duels, Duel $duel): JsonResponse
    {
        $student = $this->learning->student($request->user());
        $data = $request->validated();

        return $this->ok($duels->answer($duel, $student, (int) ($data['no'] ?? 0), (string) ($data['answer'] ?? '')));
    }

    public function duelCancel(Request $request, DuelService $duels, Duel $duel): JsonResponse
    {
        $student = $this->learning->student($request->user());

        return $this->ok($duels->state($duels->cancel($duel, $student), $duel->sideOf($student)), 'Duel bekor qilindi.');
    }

    private function authorizeLesson(Request $request, LessonSession $lesson): void
    {
        $student = $this->learning->student($request->user());
        abort_unless($lesson->student_id === $student->id, 403, 'Bu dars sizga tegishli emas.');
    }

    /* ---------------- Yordamchilar ---------------- */

    private function authorizeAttempt(Request $request, TestAttempt $attempt): Student
    {
        $student = $this->learning->student($request->user());
        abort_unless($attempt->student_id === $student->id, 403, 'Bu urinish sizga tegishli emas.');

        return $student;
    }

    private function authorizeSession(Request $request, GameSession $session): void
    {
        $student = $this->learning->student($request->user());
        abort_unless($session->student_id === $student->id, 403, 'Bu o\'yin sizga tegishli emas.');
    }

    private function hideAnswers(GameSessionResource $resource): GameSessionResource
    {
        $resource->hideAnswers = true;

        return $resource;
    }
}
