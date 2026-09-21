<?php

namespace App\Services;

use App\Models\Homework;
use App\Models\Student;
use App\Models\TestAssignment;
use App\Models\TestAttempt;
use App\Models\TestQuestion;
use App\Models\TestTemplate;
use App\Models\User;
use App\Support\Level;
use Illuminate\Support\Collection;

/**
 * O'quvchi kabineti ("learn"): kirganda faqat o'z darajasi va guruhiga mos
 * darsliklar, testlar, o'yinlar, uy ishlari ko'rinadi.
 */
class LearningService
{
    public function __construct(
        private readonly TestService $tests,
        private readonly SkillProgressService $skills,
        private readonly MediaPlayService $plays,
        private readonly CourseUnitService $units,
        private readonly GameService $games,
        private readonly MeetingService $meetings,
        private readonly CertificateService $certificates,
    ) {}

    /** Joriy foydalanuvchining o'quvchi profili (student roli bo'lmasa 403). */
    public function student(User $user): Student
    {
        $student = Student::with('schoolClass')->where('user_id', $user->id)->first();

        abort_unless($student, 403, 'Bu bo\'lim faqat o\'quvchilar uchun.');
        abort_if($student->status !== 'active', 403, 'Akkaunt faol emas. Administratorga murojaat qiling.');

        return $student;
    }

    /** Bosh sahifa: daraja, guruh, ko'nikmalar, kutilayotgan ishlar, streak, liderbord. */
    public function dashboard(Student $student): array
    {
        $level = $student->effectiveLevel();
        $pending = $this->assignedTests($student)->where('state.can_start', true)->filter(fn ($a) => ! $a['completed'] || $a['state']['remaining'] === null || $a['state']['remaining'] > 0);
        $homeworks = Homework::where('status', 'active')
            ->where(fn ($q) => $q->where('student_id', $student->id)->orWhere('class_id', $student->class_id))
            ->whereDoesntHave('submissions', fn ($q) => $q->where('student_id', $student->id))
            ->count();

        $this->touchStreak($student);

        return [
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'photo' => $student->photo,
                'level' => $level,
                'level_title' => Level::title($level),
                'next_level' => Level::next($level),
                'track' => $student->effectiveTrack(),
                'track_title' => config('academy.tracks.'.$student->effectiveTrack()),
                'group' => $student->schoolClass?->name,
                'preferred_language' => $student->translationLanguage(),
                'streak_days' => $student->streak_days,
                'speech_game_score' => $student->speech_game_score,
                'monthly_hours' => $student->monthly_hours,
            ],
            'skills' => $level ? $this->skills->forLevel($student, $level) : [],
            'overall_percent' => $level ? $this->skills->overall($student, $level) : 0,
            'pass_percent' => config('academy.pass_percent'),
            'pending' => [
                'tests' => $pending->count(),
                'homeworks' => $homeworks,
            ],
            'upcoming_meetings' => $this->meetings->forStudent($student)->take(3)->map(fn ($m) => [
                'id' => $m->id, 'title' => $m->title, 'starts_at' => $m->starts_at->toDateTimeString(), 'link' => $m->link,
            ])->values(),
            'certificate' => collect($this->certificates->eligibility($student))->only(['eligible', 'reason', 'available_from', 'period'])->all(),
            'leaderboard' => $this->games->leaderboard(null, ['level' => $level], 5),
            'recent_results' => TestAttempt::where('student_id', $student->id)
                ->whereIn('status', [TestAttempt::STATUS_GRADED, TestAttempt::STATUS_SUBMITTED])
                ->with('assignment.template:id,title,kind,level')
                ->orderByDesc('submitted_at')->limit(5)->get()
                ->map(fn ($a) => ['attempt_id' => $a->id, 'title' => $a->assignment?->template?->title, 'kind' => $a->assignment?->template?->kind, 'percent' => (float) $a->percent, 'status' => $a->status, 'date' => $a->submitted_at?->toDateTimeString()])
                ->values(),
        ];
    }

    /**
     * O'quvchiga biriktirilgan testlar (guruhi yoki shaxsan) + o'z darajasidagi ochiq mashqlar.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function assignedTests(Student $student, array $filters = []): Collection
    {
        $assignments = TestAssignment::where('status', 'active')
            ->where(fn ($q) => $q->where('student_id', $student->id)->orWhere('class_id', $student->class_id))
            ->whereHas('template', fn ($q) => $q
                ->when($filters['kind'] ?? null, fn ($w, $k) => $w->where('kind', $k))
                ->when($filters['skill'] ?? null, fn ($w, $s) => $w->where(fn ($x) => $x->where('skill', $s)->orWhereHas('sections', fn ($sec) => $sec->where('skill', $s)))))
            ->with(['template.sections', 'teacher.user'])
            ->orderByDesc('id')
            ->get();

        return $assignments->map(function (TestAssignment $assignment) use ($student) {
            $template = $assignment->template;
            $state = $this->tests->attemptsState($assignment, $student);

            return [
                'assignment_id' => $assignment->id,
                'template_id' => $template->id,
                'title' => $template->title,
                'description' => $template->description,
                'kind' => $template->kind,
                'level' => $template->level,
                'skills' => $template->skills(),
                'question_count' => $template->question_count,
                'duration_minutes' => $template->duration_minutes,
                'max_plays' => $template->playsLimit(),
                'pass_percent' => $template->pass_percent,
                'starts_at' => $assignment->starts_at?->toDateTimeString(),
                'due_at' => $assignment->due_at?->toDateTimeString(),
                'teacher' => $assignment->teacher?->user?->full_name,
                'completed' => $state['used'] > 0,
                'best_percent' => $state['best_percent'],
                'state' => [
                    'used' => $state['used'],
                    'limit' => $state['limit'],
                    'remaining' => $state['remaining'],
                    'can_start' => $state['can_start'],
                    'in_progress_attempt_id' => $state['in_progress']?->id,
                ],
            ];
        })->values();
    }

    /**
     * O'z darajasidagi nashr qilingan mashq testlari (biriktirilmagan bo'lsa ham).
     */
    public function practiceTests(Student $student, array $filters = []): Collection
    {
        $level = $filters['level'] ?? $student->effectiveLevel();

        return TestTemplate::where('is_published', true)
            ->where('kind', TestTemplate::KIND_PRACTICE)
            ->where('level', $level)
            ->whereIn('track', array_unique([$student->effectiveTrack(), 'general']))
            ->when($filters['skill'] ?? null, fn ($q, $s) => $q->where(fn ($x) => $x->where('skill', $s)->orWhereHas('sections', fn ($sec) => $sec->where('skill', $s))))
            ->with('sections')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TestTemplate $t) => [
                'template_id' => $t->id,
                'title' => $t->title,
                'description' => $t->description,
                'level' => $t->level,
                'skills' => $t->skills(),
                'question_count' => $t->question_count,
                'duration_minutes' => $t->duration_minutes,
                'course_unit_id' => $t->course_unit_id,
            ])->values();
    }

    /**
     * Mashq testini biriktirmasiz boshlash — o'quvchi uchun shaxsiy biriktirma yaratiladi.
     */
    public function startPractice(TestTemplate $template, Student $student): TestAttempt
    {
        abort_unless($template->is_published && $template->kind === TestTemplate::KIND_PRACTICE, 422, 'Bu test mashq sifatida ochiq emas.');
        abort_unless($template->level === null || $template->level === $student->effectiveLevel() || Level::ordinal($template->level) < Level::ordinal($student->effectiveLevel()), 403, 'Bu test sizning darajangizga mos emas.');

        $assignment = TestAssignment::firstOrCreate(
            ['test_template_id' => $template->id, 'student_id' => $student->id, 'target' => TestAssignment::TARGET_STUDENT],
            ['school_id' => config('school.id'), 'teacher_id' => $template->teacher_id, 'starts_at' => now(), 'status' => 'active'],
        );

        return $this->tests->start($assignment, $student);
    }

    /**
     * Urinishning o'quvchiga ko'rinadigan holati: bo'limlar, savollar (to'g'ri javobsiz),
     * media eshitish holati, vaqt.
     */
    public function attemptView(TestAttempt $attempt, Student $student): array
    {
        abort_unless($attempt->student_id === $student->id, 403);

        $attempt->load(['assignment.template.sections', 'answers']);
        $template = $attempt->assignment->template;
        $questions = TestQuestion::with('options')->whereIn('id', $attempt->question_order ?? [])->get()->keyBy('id');
        $answers = $attempt->answers->keyBy('test_question_id');
        $lang = $student->translationLanguage();
        $finished = $attempt->isFinished();
        $showAnswers = $finished && $template->show_answers;

        $ordered = collect($attempt->question_order ?? [])->map(fn ($id) => $questions->get($id))->filter();
        $bySection = $ordered->groupBy(fn ($q) => $q->test_section_id ?? 0);

        $sections = $template->sections->map(function ($section) use ($bySection, $attempt, $student, $answers, $lang, $showAnswers, $template, $finished) {
            $progress = ($attempt->section_progress ?? [])[(string) $section->id] ?? null;

            return [
                'id' => $section->id,
                'skill' => $section->skill,
                'skill_label' => config("academy.skills.{$section->skill}"),
                'title' => $section->title,
                'instructions' => $section->instructions,
                'passage' => $section->passage,
                'media_url' => $section->media_path ? asset('storage/'.$section->media_path) : $section->media_url,
                'has_media' => $section->hasMedia(),
                // Audio fayl bo'lmasa brauzer TTS (speechSynthesis, it-IT) shu matnni o'qiydi
                'tts_text' => $section->hasMedia() ? null : $section->media_script,
                'play_state' => $section->hasMedia() ? $this->plays->status($student, $section, $attempt) : null,
                'time_limit_minutes' => $section->timeLimit(),
                'started_at' => $progress['started_at'] ?? null,
                'expires_at' => $progress['expires_at'] ?? null,
                'questions' => ($bySection->get($section->id) ?? collect())->map(fn ($q) => $this->questionView($q, $answers->get($q->id), $lang, $showAnswers, $student, $attempt, $finished, $template))->values(),
            ];
        })->values()->all();

        if ($bySection->has(0)) {
            $sections[] = [
                'id' => null,
                'skill' => $template->skill,
                'skill_label' => config("academy.skills.{$template->skill}"),
                'title' => $template->title,
                'questions' => $bySection->get(0)->map(fn ($q) => $this->questionView($q, $answers->get($q->id), $lang, $showAnswers, $student, $attempt, $finished, $template))->values(),
            ];
        }

        return [
            'attempt' => [
                'id' => $attempt->id,
                'attempt_no' => $attempt->attempt_no,
                'status' => $attempt->status,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'expires_at' => $attempt->expires_at?->toIso8601String(),
                'seconds_left' => $attempt->expires_at && ! $finished ? max(0, (int) now()->diffInSeconds($attempt->expires_at, false)) : null,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'total_score' => (float) $attempt->total_score,
                'max_score' => (float) $attempt->max_score,
                'percent' => (float) $attempt->percent,
                'passed' => $finished ? (float) $attempt->percent >= (float) ($template->pass_percent ?? config('academy.pass_percent')) : null,
                'summary' => $finished ? $attempt->ai_summary : null,
            ],
            'test' => [
                'id' => $template->id,
                'title' => $template->title,
                'kind' => $template->kind,
                'level' => $template->level,
                'duration_minutes' => $template->duration_minutes,
                'max_plays' => $template->playsLimit(),
                'show_answers' => $template->show_answers,
            ],
            'sections' => $sections,
        ];
    }

    private function questionView(TestQuestion $q, $answer, string $lang, bool $showAnswers, Student $student, TestAttempt $attempt, bool $finished, TestTemplate $template): array
    {
        $options = $q->options;

        if ($template->shuffle_options && ! $finished) {
            $order = $answer?->option_order ?: $options->pluck('id')->shuffle()->values()->all();
            $options = $options->sortBy(fn ($o) => array_search($o->id, $order, true))->values();
        }

        return [
            'id' => $q->id,
            'type' => $q->type,
            'skill' => $q->skill,
            'answer_mode' => $q->answer_mode,
            'body' => $q->body,
            'translation' => $q->translations[$lang] ?? null,
            'score' => (float) $q->score,
            'media_url' => $q->media_path ? asset('storage/'.$q->media_path) : null,
            'tts_text' => $q->hasMedia() ? null : $q->media_script,
            'play_state' => $q->hasMedia() ? $this->plays->status($student, $q, $attempt) : null,
            'meta' => $q->meta,
            'options' => $options->map(fn ($o) => ['id' => $o->id, 'body' => $o->body] + ($showAnswers ? ['is_correct' => $o->is_correct] : []))->values(),
            'answer' => $answer ? [
                'selected_option_ids' => $answer->selected_option_ids,
                'answer_text' => $answer->answer_text,
                'transcript' => $answer->transcript,
                'audio_url' => $answer->audio_path ? asset('storage/'.$answer->audio_path) : null,
                'is_correct' => $finished ? $answer->is_correct : null,
                'score' => $finished ? (float) $answer->score : null,
                'similarity' => $finished ? $answer->similarity : null,
                'ai_feedback' => $finished ? $answer->ai_feedback : null,
                'feedback' => $finished ? $answer->feedback : null,
            ] : null,
            'correct_answer' => $showAnswers ? $q->correct_answer : null,
            'model_answer' => $showAnswers ? $q->model_answer : null,
            'explanation' => $showAnswers ? $q->explanation : null,
        ];
    }

    /** O'quvchining barcha natijalari */
    public function results(Student $student, array $filters = []): Collection
    {
        return TestAttempt::where('student_id', $student->id)
            ->where('status', '!=', TestAttempt::STATUS_IN_PROGRESS)
            ->with('assignment.template:id,title,kind,level,skill,pass_percent')
            ->when($filters['kind'] ?? null, fn ($q, $k) => $q->whereHas('assignment.template', fn ($t) => $t->where('kind', $k)))
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (TestAttempt $a) => [
                'attempt_id' => $a->id,
                'attempt_no' => $a->attempt_no,
                'title' => $a->assignment?->template?->title,
                'kind' => $a->assignment?->template?->kind,
                'level' => $a->assignment?->template?->level,
                'status' => $a->status,
                'percent' => (float) $a->percent,
                'passed' => (float) $a->percent >= (float) ($a->assignment?->template?->pass_percent ?? config('academy.pass_percent')),
                'by_skill' => $a->ai_summary['by_skill'] ?? null,
                'submitted_at' => $a->submitted_at?->toDateTimeString(),
            ])->values();
    }

    /** Kunlik faollik ketma-ketligi (streak). */
    private function touchStreak(Student $student): void
    {
        $last = $student->last_activity_at;
        $streak = $student->streak_days;

        if (! $last || ! $last->isToday()) {
            $streak = $last && $last->isYesterday() ? $streak + 1 : 1;
        }

        $student->forceFill(['streak_days' => $streak, 'last_activity_at' => now()])->saveQuietly();
    }
}
