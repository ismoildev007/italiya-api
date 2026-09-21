<?php

namespace App\Services;

use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TestAnswer;
use App\Models\TestAssignment;
use App\Models\TestAttempt;
use App\Models\TestQuestion;
use App\Models\TestQuestionOption;
use App\Models\TestSection;
use App\Models\TestTemplate;
use App\Services\Ai\SpeechService;
use App\Support\TextSimilarity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * IELTS uslubidagi testlar: bo'limlar (listening/reading/writing/speaking…),
 * urinishlar limiti (mock = 1), bo'lim/audio eshitish limiti, vaqt chegarasi,
 * avtomatik tekshiruv (variant, true/false, o'xshashlik) va AI baholash (essay/speaking).
 */
class TestService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly VoiceTranscriptionService $transcription,
        private readonly AiEvaluationService $evaluation,
        private readonly SkillProgressService $skills,
        private readonly SpeechService $speech,
    ) {}

    /* ------------------------------------------------------------------ */
    /* Shablonlar */
    /* ------------------------------------------------------------------ */

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return TestTemplate::query()
            ->with(['subject', 'teacher.user', 'sections'])
            ->withCount('questions')
            ->search($filters['search'] ?? null, ['title', 'topic'])
            ->filter($filters, [
                'subject_id' => 'subject_id',
                'teacher_id' => 'teacher_id',
                'grade' => 'grade',
                'level' => 'level',
                'track' => 'track',
                'kind' => 'kind',
                'skill' => fn ($q, $skill) => $q->where(fn ($w) => $w->where('skill', $skill)->orWhereHas('sections', fn ($s) => $s->where('skill', $skill))),
                'course_unit_id' => 'course_unit_id',
                'generated_by' => 'generated_by',
                'is_published' => ['is_published', 'bool'],
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function withQuestions(TestTemplate $template): TestTemplate
    {
        return $template->load(['subject', 'teacher.user', 'unit', 'sections.questions.options', 'questions.options']);
    }

    public function delete(TestTemplate $template): void
    {
        $template->delete();
    }

    /**
     * Shablonni bo'limlari va savollari bilan saqlaydi (AI natijasini tahrirlagandan keyin ham,
     * noldan yozganda ham). `sections[]` yoki eski uslubdagi `questions[]` qabul qilinadi.
     */
    public function saveTemplate(array $data, ?TestTemplate $template = null): TestTemplate
    {
        return DB::transaction(function () use ($data, $template) {
            $kind = $data['kind'] ?? $template?->kind ?? TestTemplate::KIND_PRACTICE;

            $payload = [
                'school_id' => config('school.id'),
                'subject_id' => $data['subject_id'] ?? $template?->subject_id ?? Subject::query()->orderBy('id')->value('id'),
                'teacher_id' => $data['teacher_id'] ?? $template?->teacher_id ?? Teacher::where('user_id', auth()->id())->value('id') ?? Teacher::orderBy('id')->value('id'),
                'grade' => $data['grade'] ?? $template?->grade,
                'level' => $data['level'] ?? $template?->level,
                'track' => $data['track'] ?? $template?->track ?? 'general',
                'kind' => $kind,
                'skill' => $data['skill'] ?? $template?->skill,
                'language' => $data['language'] ?? $template?->language ?? config('academy.target_language'),
                'title' => $data['title'] ?? $template?->title,
                'topic' => $data['topic'] ?? $template?->topic,
                'description' => $data['description'] ?? $template?->description,
                'difficulty' => $data['difficulty'] ?? $template?->difficulty ?? 'medium',
                'generated_by' => $data['generated_by'] ?? $template?->generated_by ?? TestTemplate::SOURCE_MANUAL,
                'ai_model' => $data['ai_model'] ?? $template?->ai_model,
                'ai_prompt' => $data['ai_prompt'] ?? $template?->ai_prompt,
                'ai_generated_at' => ($data['generated_by'] ?? null) === TestTemplate::SOURCE_AI ? now() : $template?->ai_generated_at,
                'shuffle_questions' => $data['shuffle_questions'] ?? $template?->shuffle_questions ?? true,
                'shuffle_options' => $data['shuffle_options'] ?? $template?->shuffle_options ?? true,
                'duration_minutes' => $data['duration_minutes'] ?? $template?->duration_minutes ?? 30,
                'max_attempts' => array_key_exists('max_attempts', $data) ? $data['max_attempts'] : ($template?->max_attempts ?? config("academy.defaults.kinds.{$kind}.max_attempts")),
                'max_plays' => array_key_exists('max_plays', $data) ? $data['max_plays'] : ($template?->max_plays ?? config("academy.defaults.kinds.{$kind}.max_plays")),
                'auto_grade_ai' => $data['auto_grade_ai'] ?? $template?->auto_grade_ai ?? config("academy.defaults.kinds.{$kind}.auto_grade_ai", true),
                'show_answers' => $data['show_answers'] ?? $template?->show_answers ?? ($kind === TestTemplate::KIND_PRACTICE),
                'pass_percent' => $data['pass_percent'] ?? $template?->pass_percent ?? config('academy.pass_percent'),
                'time_limits' => $data['time_limits'] ?? $template?->time_limits,
                'course_unit_id' => $data['course_unit_id'] ?? $template?->course_unit_id,
                'is_published' => $data['is_published'] ?? $template?->is_published ?? false,
                'created_by' => $template?->created_by ?? auth()->id(),
            ];

            $template = $template
                ? tap($template)->update($payload)
                : TestTemplate::create($payload);

            if (array_key_exists('sections', $data)) {
                $this->syncSections($template, $data['sections']);
            } elseif (array_key_exists('questions', $data)) {
                $this->syncQuestions($template, null, $data['questions']);
            }

            $template->recalculateScore();

            if (! empty($data['generate_audio'])) {
                $this->generateAudio($template);
            }

            return $template->fresh(['sections.questions.options', 'questions.options', 'subject', 'teacher']);
        });
    }

    /** Bo'limlarni to'liq almashtiradi (id berilganlar yangilanadi, qolganlari o'chadi). */
    private function syncSections(TestTemplate $template, array $sections): void
    {
        $keep = [];
        $keepQuestions = [];

        foreach ($sections as $index => $data) {
            $section = ! empty($data['id'])
                ? TestSection::where('test_template_id', $template->id)->findOrFail($data['id'])
                : new TestSection(['test_template_id' => $template->id]);

            $section->fill([
                'school_id' => config('school.id'),
                'test_template_id' => $template->id,
                'order' => $data['order'] ?? $index + 1,
                'skill' => $data['skill'],
                'title' => $data['title'] ?? config("academy.skills.{$data['skill']}"),
                'instructions' => $data['instructions'] ?? null,
                'passage' => $data['passage'] ?? null,
                'media_script' => $data['media_script'] ?? null,
                'media_path' => $data['media_path'] ?? $section->media_path,
                'media_url' => $data['media_url'] ?? null,
                'max_plays' => $data['max_plays'] ?? null,
                'time_limit_minutes' => $data['time_limit_minutes'] ?? null,
                'ai_generated' => $data['ai_generated'] ?? false,
            ])->save();

            $keep[] = $section->id;
            $keepQuestions = array_merge($keepQuestions, $this->syncQuestions($template, $section, $data['questions'] ?? [], false));
        }

        TestSection::where('test_template_id', $template->id)->whereNotIn('id', $keep ?: [0])->delete();
        TestQuestion::where('test_template_id', $template->id)->whereNotIn('id', $keepQuestions ?: [0])->delete();
    }

    /**
     * Savollarni saqlaydi. `$prune` = true bo'lsa ro'yxatda yo'q savollar o'chiriladi.
     *
     * @return array<int, int> saqlangan savol id lari
     */
    private function syncQuestions(TestTemplate $template, ?TestSection $section, array $questions, bool $prune = true): array
    {
        $keepIds = [];

        foreach ($questions as $index => $data) {
            $question = ! empty($data['id'])
                ? TestQuestion::where('test_template_id', $template->id)->findOrFail($data['id'])
                : new TestQuestion(['test_template_id' => $template->id, 'school_id' => config('school.id')]);

            $type = $data['type'] ?? TestQuestion::TYPE_SINGLE;

            $question->fill([
                'school_id' => config('school.id'),
                'test_template_id' => $template->id,
                'test_section_id' => $section?->id,
                'order' => $data['order'] ?? $index + 1,
                'body' => $data['body'],
                'type' => $type,
                'skill' => $data['skill'] ?? $section?->skill ?? $template->skill,
                'answer_mode' => $type === TestQuestion::TYPE_SPEAKING ? TestQuestion::MODE_VOICE : ($data['answer_mode'] ?? TestQuestion::MODE_WRITTEN),
                'score' => $data['score'] ?? 1,
                'correct_answer' => $data['correct_answer'] ?? null,
                'model_answer' => $data['model_answer'] ?? null,
                'explanation' => $data['explanation'] ?? null,
                'media_script' => $data['media_script'] ?? null,
                'media_path' => $data['media_path'] ?? $question->media_path,
                'max_plays' => $data['max_plays'] ?? null,
                'similarity_threshold' => $data['similarity_threshold'] ?? null,
                'translations' => $data['translations'] ?? null,
                'meta' => $data['meta'] ?? null,
                'ai_generated' => $data['ai_generated'] ?? false,
            ])->save();

            $keepIds[] = $question->id;

            $question->options()->delete();

            foreach ($data['options'] ?? [] as $optionIndex => $option) {
                TestQuestionOption::create([
                    'test_question_id' => $question->id,
                    'order' => $option['order'] ?? $optionIndex + 1,
                    'body' => $option['body'],
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                ]);
            }
        }

        if ($prune) {
            TestQuestion::where('test_template_id', $template->id)->whereNotIn('id', $keepIds ?: [0])->delete();
        }

        return $keepIds;
    }

    /**
     * Listening bo'limlari va savollari uchun OpenAI TTS orqali audio yaratadi
     * (media_script bor, media_path yo'q bo'lganlar). Yaratilganlar sonini qaytaradi.
     */
    public function generateAudio(TestTemplate $template, bool $force = false): int
    {
        $count = 0;
        $instructions = 'Speak clearly in natural Italian at a pace suitable for language learners at level '.($template->level ?? 'B1').'.';

        foreach ($template->sections()->get() as $section) {
            if (filled($section->media_script) && ($force || ! $section->media_path)) {
                $path = $this->speech->synthesize($section->media_script, "tests/{$template->id}/sections", null, $instructions);

                if ($path) {
                    $section->update(['media_path' => $path]);
                    $count++;
                }
            }
        }

        foreach ($template->questions()->whereNotNull('media_script')->get() as $question) {
            if ($force || ! $question->media_path) {
                $path = $this->speech->synthesize($question->media_script, "tests/{$template->id}/questions", null, $instructions);

                if ($path) {
                    $question->update(['media_path' => $path]);
                    $count++;
                }
            }
        }

        return $count;
    }

    public function publish(TestTemplate $template, bool $published = true): TestTemplate
    {
        $template->update(['is_published' => $published]);

        return $template->fresh();
    }

    /* ------------------------------------------------------------------ */
    /* Biriktirish */
    /* ------------------------------------------------------------------ */

    public function paginateAssignments(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return TestAssignment::query()
            ->with(['template.subject', 'schoolClass', 'student', 'teacher.user'])
            ->withCount('attempts')
            ->filter($filters, [
                'class_id' => 'class_id',
                'student_id' => 'student_id',
                'teacher_id' => 'teacher_id',
                'status' => 'status',
                'kind' => ['template.kind', 'has'],
                'level' => ['template.level', 'has'],
            ])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Testni guruhga yoki bitta o'quvchiga biriktiradi va o'quvchilarga xabar yuboradi. */
    public function assign(TestTemplate $template, array $data): TestAssignment
    {
        $target = ! empty($data['student_id']) ? TestAssignment::TARGET_STUDENT : TestAssignment::TARGET_CLASS;

        $assignment = TestAssignment::create([
            'school_id' => config('school.id'),
            'test_template_id' => $template->id,
            'class_id' => $target === TestAssignment::TARGET_CLASS ? $data['class_id'] : null,
            'student_id' => $target === TestAssignment::TARGET_STUDENT ? $data['student_id'] : null,
            'teacher_id' => $data['teacher_id'] ?? $template->teacher_id,
            'target' => $target,
            'starts_at' => $data['starts_at'] ?? now(),
            'due_at' => $data['due_at'] ?? null,
            'status' => 'active',
        ]);

        if (! $template->is_published) {
            $template->update(['is_published' => true]);
        }

        $this->notifications->testAssigned($assignment->load(['template', 'schoolClass', 'student']));

        return $assignment;
    }

    /** Biriktirma bo'yicha barcha natijalar va xulosa. */
    public function results(TestAssignment $assignment): array
    {
        $attempts = $assignment->attempts()
            ->with(['student', 'answers.question'])
            ->orderByDesc('percent')
            ->get();

        return [
            'assignment' => $assignment->load(['template.subject', 'schoolClass', 'student']),
            'summary' => [
                'attempts' => $attempts->count(),
                'students' => $attempts->pluck('student_id')->unique()->count(),
                'average_percent' => $attempts->count() ? round($attempts->avg('percent'), 2) : 0,
                'best_percent' => (float) ($attempts->max('percent') ?? 0),
                'graded' => $attempts->where('status', TestAttempt::STATUS_GRADED)->count(),
                'awaiting_review' => $attempts->where('status', TestAttempt::STATUS_SUBMITTED)->count(),
                'passed' => $attempts->filter(fn ($a) => (float) $a->percent >= (float) ($assignment->template->pass_percent ?? config('academy.pass_percent')))->count(),
            ],
            'attempts' => $attempts,
        ];
    }

    public function attemptDetails(TestAttempt $attempt): TestAttempt
    {
        return $attempt->load(['student', 'assignment.template.subject', 'assignment.template.sections', 'answers.question.options']);
    }

    /* ------------------------------------------------------------------ */
    /* O'quvchi: boshlash → javob → topshirish */
    /* ------------------------------------------------------------------ */

    /**
     * Urinishlar holati: nechta ishlatilgan, limit, boshlash mumkinmi.
     *
     * @return array{used:int, limit:?int, remaining:?int, can_start:bool, in_progress:?TestAttempt}
     */
    public function attemptsState(TestAssignment $assignment, Student $student): array
    {
        $template = $assignment->template;
        $limit = $template->attemptsLimit();

        $attempts = TestAttempt::where('test_assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->orderBy('attempt_no')
            ->get();

        $inProgress = $attempts->first(fn ($a) => $a->isInProgress() && ! $a->isExpired());
        $used = $attempts->filter(fn ($a) => $a->isFinished() || $a->isExpired())->count();

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'can_start' => $inProgress !== null || $limit === null || $used < $limit,
            'in_progress' => $inProgress,
            'best_percent' => (float) ($attempts->where('status', TestAttempt::STATUS_GRADED)->max('percent') ?? 0),
        ];
    }

    /**
     * O'quvchi testni boshlaydi. Mock — 1 marta; boshqa turlar limiti shablon/config bo'yicha.
     * Savollar tartibi shu o'quvchi uchun (bo'lim ichida) aralashtiriladi, muddat belgilanadi.
     */
    public function start(TestAssignment $assignment, Student $student, string $source = 'web'): TestAttempt
    {
        abort_if($assignment->status !== 'active', 422, 'Bu test yopilgan.');
        abort_if($assignment->starts_at && $assignment->starts_at->isFuture(), 422, 'Test hali boshlanmagan.');
        abort_if($assignment->due_at && $assignment->due_at->isPast(), 422, 'Test muddati tugagan.');

        $ownsIt = $assignment->student_id === $student->id || ($assignment->class_id && $assignment->class_id === $student->class_id);
        abort_unless($ownsIt, 403, 'Bu test sizga biriktirilmagan.');

        $template = $assignment->template;
        $state = $this->attemptsState($assignment, $student);

        if ($state['in_progress']) {
            return $state['in_progress'];
        }

        // Muddati o'tgan, lekin topshirilmagan urinishlarni yopamiz
        TestAttempt::where('test_assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->where('status', TestAttempt::STATUS_IN_PROGRESS)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get()
            ->each(fn (TestAttempt $expired) => $this->submit($expired, [], true));

        $limit = $template->attemptsLimit();
        abort_if($limit !== null && $state['used'] >= $limit, 422, "Urinishlar soni tugagan ({$limit} marta).");

        $questions = $template->questions()->with('section')->get();

        $ordered = $questions
            ->sortBy(fn ($q) => sprintf('%03d-%03d', $q->section?->order ?? 0, $q->order))
            ->groupBy(fn ($q) => $q->test_section_id ?? 0)
            ->flatMap(fn ($group) => $template->shuffle_questions ? $group->shuffle() : $group)
            ->pluck('id')->values()->all();

        $attemptNo = (int) TestAttempt::where('test_assignment_id', $assignment->id)->where('student_id', $student->id)->max('attempt_no') + 1;

        return TestAttempt::create([
            'school_id' => config('school.id'),
            'test_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'attempt_no' => $attemptNo,
            'started_at' => now(),
            'expires_at' => $template->duration_minutes ? now()->addMinutes($template->duration_minutes) : null,
            'status' => TestAttempt::STATUS_IN_PROGRESS,
            'question_order' => $ordered,
            'section_progress' => [],
            'max_score' => (float) $template->total_score,
            'source' => $source,
        ]);
    }

    /** Bo'limga kirish — bo'lim vaqti shu paytdan hisoblanadi (reading/writing taymeri). */
    public function openSection(TestAttempt $attempt, TestSection $section): array
    {
        $this->assertOpen($attempt);

        $progress = $attempt->section_progress ?? [];
        $key = (string) $section->id;

        if (! isset($progress[$key])) {
            $minutes = $section->timeLimit();
            $progress[$key] = [
                'skill' => $section->skill,
                'started_at' => now()->toIso8601String(),
                'expires_at' => $minutes ? now()->addMinutes($minutes)->toIso8601String() : null,
            ];
            $attempt->update(['section_progress' => $progress]);
        }

        return $progress[$key];
    }

    /**
     * Bitta savolga javobni saqlaydi (autosave). Ovozli javob — audio yuklanadi va STT qilinadi.
     */
    public function answer(TestAttempt $attempt, int $questionId, array $data, ?UploadedFile $audio = null): TestAnswer
    {
        $this->assertOpen($attempt);

        $question = TestQuestion::where('test_template_id', $attempt->assignment->test_template_id)->findOrFail($questionId);

        if ($question->test_section_id && ! $this->sectionOpen($attempt, $question->test_section_id)) {
            abort(422, 'Bu bo\'lim vaqti tugagan.');
        }

        $answer = TestAnswer::firstOrNew([
            'test_attempt_id' => $attempt->id,
            'test_question_id' => $question->id,
        ]);

        $answer->fill([
            'school_id' => config('school.id'),
            'selected_option_ids' => $data['selected_option_ids'] ?? $answer->selected_option_ids,
            'answer_text' => $data['answer_text'] ?? $answer->answer_text,
            'option_order' => $data['option_order'] ?? $answer->option_order,
        ])->save();

        if ($audio) {
            $answer = $this->transcription->store($answer, $audio);
        } elseif (! empty($data['transcript'])) {
            $answer->update(['transcript' => $data['transcript'], 'transcription_provider' => 'client', 'transcribed_at' => now()]);
        }

        return $answer->fresh('question');
    }

    /**
     * Topshirish: avtomatik tekshiruv + AI baholash. `$answers` — qo'shimcha (autosave qilinmagan) javoblar.
     * Ochiq javoblar AI bo'lmasa o'qituvchi tekshiruvini kutadi.
     */
    public function submit(TestAttempt $attempt, array $answers = [], bool $expired = false): TestAttempt
    {
        abort_if($attempt->isFinished(), 422, 'Bu urinish allaqachon topshirilgan.');

        foreach ($answers as $data) {
            if (! empty($data['question_id'])) {
                try {
                    $this->answer($attempt, (int) $data['question_id'], $data);
                } catch (HttpException) {
                    // vaqt tugagan bo'lim — javob e'tiborga olinmaydi
                }
            }
        }

        return DB::transaction(function () use ($attempt, $expired) {
            $template = $attempt->assignment->template;
            $questions = $template->questions()->with('options')->get()->keyBy('id');
            $answers = $attempt->answers()->get()->keyBy('test_question_id');
            $level = $template->level ?: $attempt->student?->effectiveLevel();

            $earned = 0.0;
            $needsReview = false;

            foreach ($questions as $question) {
                $answer = $answers->get($question->id) ?? TestAnswer::create([
                    'school_id' => config('school.id'),
                    'test_attempt_id' => $attempt->id,
                    'test_question_id' => $question->id,
                ]);

                [$isCorrect, $score, $similarity, $reviewed] = $this->autoGrade($question, $answer, $template->auto_grade_ai, $level);

                if (! $reviewed) {
                    $needsReview = true;
                }

                $earned += $score;

                $answer->update(['is_correct' => $isCorrect, 'score' => $score, 'similarity' => $similarity]);
            }

            $max = max(1.0, (float) $attempt->max_score);
            $percent = round($earned / $max * 100, 2);

            $attempt->update([
                'submitted_at' => now(),
                'status' => $expired && $earned === 0.0 ? TestAttempt::STATUS_EXPIRED : ($needsReview ? TestAttempt::STATUS_SUBMITTED : TestAttempt::STATUS_GRADED),
                'total_score' => $earned,
                'percent' => $percent,
                'graded_at' => $needsReview ? null : now(),
                'ai_summary' => $this->summarize($attempt->fresh('answers.question')),
            ]);

            $attempt = $attempt->fresh(['answers.question', 'assignment.template', 'student']);

            $this->skills->applyAttempt($attempt);

            if (! $needsReview) {
                $this->createGradeFromAttempt($attempt);
            }

            return $attempt;
        });
    }

    /**
     * @return array{0: ?bool, 1: float, 2: ?float, 3: bool} [to'g'ri?, ball, o'xshashlik, baholandi?]
     */
    private function autoGrade(TestQuestion $question, TestAnswer $answer, bool $useAi, ?string $level): array
    {
        $max = (float) $question->score;

        if ($question->isChoice()) {
            $selected = collect($answer->selected_option_ids ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all();
            $correct = $question->options->where('is_correct', true)->pluck('id')->sort()->values()->all();
            $isCorrect = $selected !== [] && $selected === $correct;

            return [$isCorrect, $isCorrect ? $max : 0.0, null, true];
        }

        if ($question->isTrueFalse()) {
            $given = strtolower(trim((string) $answer->effectiveText()));
            $isCorrect = $given !== '' && $given === strtolower(trim((string) $question->correct_answer));

            return [$isCorrect, $isCorrect ? $max : 0.0, null, true];
        }

        if ($question->isSimilarity()) {
            $given = (string) $answer->effectiveText();
            $similarity = TextSimilarity::ratio($given, (string) $question->correct_answer);
            $isCorrect = $given !== '' && $similarity >= $question->threshold();

            return [$isCorrect, $isCorrect ? $max : 0.0, round($similarity * 100, 2), true];
        }

        // Essay / speaking: AI baholaydi (yoqilgan bo'lsa), aks holda o'qituvchi
        if ($useAi && $this->evaluation->isAvailable()) {
            $evaluated = $this->evaluation->evaluate($answer, $level);

            if ($evaluated && $evaluated->ai_score !== null) {
                $score = (float) $evaluated->ai_score;

                return [$score >= $max * 0.6, $score, null, true];
            }
        }

        return [null, 0.0, null, false];
    }

    /** Ko'nikmalar bo'yicha qisqa xulosa (natija ekrani uchun). */
    private function summarize(TestAttempt $attempt): array
    {
        $bySkill = $attempt->answers->groupBy(fn ($a) => $a->question?->skill ?: 'general');

        return [
            'by_skill' => $bySkill->map(function ($answers) {
                $max = (float) $answers->sum(fn ($a) => (float) $a->question?->score);
                $earned = (float) $answers->sum(fn ($a) => (float) $a->score);

                return [
                    'score' => $earned,
                    'max' => $max,
                    'percent' => $max > 0 ? round($earned / $max * 100, 2) : 0,
                    'correct' => $answers->where('is_correct', true)->count(),
                    'total' => $answers->count(),
                ];
            })->all(),
            'ai_feedback' => $attempt->answers
                ->filter(fn ($a) => is_array($a->ai_feedback) && ! empty($a->ai_feedback['feedback']))
                ->map(fn ($a) => ['question_id' => $a->test_question_id, 'feedback' => $a->ai_feedback['feedback']])
                ->values()->all(),
        ];
    }

    /**
     * O'qituvchi qo'lda baholaydi (AI bahosini ham o'zgartira oladi).
     *
     * @param  array<int, array{answer_id:int, score:float, feedback?:string, is_correct?:bool}>  $scores
     */
    public function grade(TestAttempt $attempt, array $scores): TestAttempt
    {
        return DB::transaction(function () use ($attempt, $scores) {
            foreach ($scores as $item) {
                TestAnswer::where('test_attempt_id', $attempt->id)
                    ->whereKey($item['answer_id'])
                    ->update(array_filter([
                        'score' => $item['score'],
                        'feedback' => $item['feedback'] ?? null,
                        'is_correct' => $item['is_correct'] ?? null,
                    ], fn ($v) => $v !== null));
            }

            $earned = (float) TestAnswer::where('test_attempt_id', $attempt->id)->sum('score');
            $max = max(1.0, (float) $attempt->max_score);

            $attempt->update([
                'total_score' => $earned,
                'percent' => round($earned / $max * 100, 2),
                'status' => TestAttempt::STATUS_GRADED,
                'graded_by' => auth()->id(),
                'graded_at' => now(),
            ]);

            $attempt = $attempt->fresh(['answers.question', 'assignment.template', 'student']);
            $this->skills->applyAttempt($attempt);
            $this->createGradeFromAttempt($attempt);

            return $attempt->fresh('answers');
        });
    }

    /* ------------------------------------------------------------------ */
    /* Yordamchilar */
    /* ------------------------------------------------------------------ */

    public function assertOpen(TestAttempt $attempt): void
    {
        abort_if($attempt->isFinished(), 422, 'Bu urinish yakunlangan.');

        if ($attempt->isExpired()) {
            $this->submit($attempt, [], true);
            abort(422, 'Test vaqti tugadi — javoblar avtomatik topshirildi.');
        }
    }

    private function sectionOpen(TestAttempt $attempt, int $sectionId): bool
    {
        $info = ($attempt->section_progress ?? [])[(string) $sectionId] ?? null;

        if (! $info || empty($info['expires_at'])) {
            return true;
        }

        return now()->lessThanOrEqualTo(Carbon::parse($info['expires_at']));
    }

    /** Test natijasini jurnalga baho sifatida yozadi (ota-onaga xabar bilan). */
    private function createGradeFromAttempt(TestAttempt $attempt): void
    {
        $template = $attempt->assignment->template;
        $percent = (float) $attempt->percent;

        if (! $template->subject_id) {
            return;
        }

        $grade = Grade::updateOrCreate(
            ['source_type' => TestAttempt::class, 'source_id' => $attempt->id],
            [
                'school_id' => config('school.id'),
                'student_id' => $attempt->student_id,
                'subject_id' => $template->subject_id,
                'class_id' => $attempt->student->class_id,
                'teacher_id' => $template->teacher_id,
                'value' => match (true) {
                    $percent >= 86 => 5,
                    $percent >= 70 => 4,
                    $percent >= 55 => 3,
                    default => 2,
                },
                'max_value' => 5,
                'type' => $template->kind === TestTemplate::KIND_MOCK ? 'mock' : 'test',
                'date' => now()->toDateString(),
                'comment' => "«{$template->title}» ({$template->level}) — {$percent}%",
            ]
        );

        $this->notifications->gradeCreated($grade->load(['student', 'subject']));
    }
}
