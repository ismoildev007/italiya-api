<?php

namespace App\Http\Controllers;

use App\Http\Requests\Test\AssignTestRequest;
use App\Http\Requests\Test\GenerateTestRequest;
use App\Http\Requests\Test\GradeAttemptRequest;
use App\Http\Requests\Test\ImportTestRequest;
use App\Http\Requests\Test\IndexAssignmentRequest;
use App\Http\Requests\Test\IndexTestRequest;
use App\Http\Requests\Test\StoreTestRequest;
use App\Http\Requests\Test\UpdateTestRequest;
use App\Http\Resources\TestAssignmentResource;
use App\Http\Resources\TestAttemptResource;
use App\Http\Resources\TestTemplateResource;
use App\Models\TestAssignment;
use App\Models\TestAttempt;
use App\Models\TestTemplate;
use App\Services\AiTestGeneratorService;
use App\Services\TestService;
use App\Services\VocabImportService;
use App\Support\Feature;
use App\Support\SpreadsheetReader;
use App\Support\TestSheetParser;
use App\Support\VocabSheetParser;
use Illuminate\Http\JsonResponse;

/**
 * Testlar: AI generatsiyasi → o'qituvchi tahriri → shablon → biriktirish → natijalar.
 */
class TestController extends Controller
{
    public function __construct(
        private readonly TestService $tests,
    ) {}

    public function index(IndexTestRequest $request): JsonResponse
    {
        return $this->ok(TestTemplateResource::collection(
            $this->tests->paginate($request->filters(), $request->perPage())
        ));
    }

    /**
     * 1-2 bosqich: AI orqali savollar generatsiyasi.
     * Natija BAZAGA YOZILMAYDI — o'qituvchi tahrirlab, keyin `store` bilan saqlaydi.
     */
    public function generate(GenerateTestRequest $request, AiTestGeneratorService $ai): JsonResponse
    {
        abort_unless(Feature::ai(), 422, 'AI funksiyasi o\'chirilgan. Yoqish uchun .env da AI_ENABLED=true.');

        return $this->ok($ai->generate($request->validated()), 'Savollar generatsiya qilindi.');
    }

    /**
     * Excel/CSV dan savollar ro'yxati — BAZAGA YOZILMAYDI. O'qituvchi ro'yxatda
     * to'g'ri javoblarni belgilab (radio / checkbox), keyin `store` bilan saqlaydi.
     * Lug'at formatidagi fayl (so'z | tarjima) berilsa variantli savollar avtomatik tuziladi.
     */
    public function importPreview(ImportTestRequest $request, VocabImportService $vocabImport): JsonResponse
    {
        $file = $request->file('file');
        $rows = SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalExtension());

        abort_if(count($rows) < 1, 422, 'Fayl bo\'sh.');

        $vocab = VocabSheetParser::parse($rows);

        if (count($vocab['items']) >= 2 && VocabSheetParser::hasHeader($rows)) {
            $count = min($request->integer('question_count') ?: min(count($vocab['items']), 30), count($vocab['items']));

            return $this->ok([
                'source' => 'vocabulary',
                'title' => $vocab['title'],
                'questions' => $vocabImport->buildQuestions($vocab['items'], $count),
                'unmarked' => 0,
            ], count($vocab['items']).' ta so\'zdan '.$count.' ta savol tuzildi.');
        }

        $parsed = TestSheetParser::parse($rows);

        abort_if($parsed['questions'] === [], 422, 'Faylda savollar topilmadi. Format: Savol | A | B | C | D | To\'g\'ri javob.');

        return $this->ok(['source' => 'questions'] + $parsed, count($parsed['questions']).' ta savol o\'qildi.');
    }

    /** 4-bosqich: yakuniy variantni shablon sifatida saqlash */
    public function store(StoreTestRequest $request): JsonResponse
    {
        return $this->created(
            new TestTemplateResource($this->tests->saveTemplate($request->validated())),
            'Test shabloni saqlandi.'
        );
    }

    public function show(TestTemplate $test): JsonResponse
    {
        return $this->ok(new TestTemplateResource($this->tests->withQuestions($test)));
    }

    public function update(UpdateTestRequest $request, TestTemplate $test): JsonResponse
    {
        return $this->updated(
            new TestTemplateResource($this->tests->saveTemplate($request->validated(), $test)),
            'Shablon yangilandi.'
        );
    }

    public function destroy(TestTemplate $test): JsonResponse
    {
        $this->tests->delete($test);

        return $this->deleted('Shablon o\'chirildi.');
    }

    /** Testni sinfga yoki bitta o'quvchiga biriktirish */
    public function assign(AssignTestRequest $request, TestTemplate $test): JsonResponse
    {
        $assignment = $this->tests->assign($test, $request->validated());

        return $this->created(
            new TestAssignmentResource($assignment->load(['template', 'schoolClass', 'student'])),
            'Test biriktirildi.'
        );
    }

    /** Biriktirilgan testlar ro'yxati */
    public function assignments(IndexAssignmentRequest $request): JsonResponse
    {
        return $this->ok(TestAssignmentResource::collection(
            $this->tests->paginateAssignments($request->filters(), $request->perPage())
        ));
    }

    /** Biriktirma bo'yicha barcha natijalar */
    public function results(TestAssignment $assignment): JsonResponse
    {
        $result = $this->tests->results($assignment);

        return $this->ok([
            'assignment' => new TestAssignmentResource($result['assignment']),
            'summary' => $result['summary'],
            'attempts' => TestAttemptResource::collection($result['attempts']),
        ]);
    }

    /** Urinishning to'liq javoblari (matn + ovoz transkripti + audio) */
    public function attempt(TestAttempt $attempt): JsonResponse
    {
        return $this->ok(new TestAttemptResource($this->tests->attemptDetails($attempt)));
    }

    /** Listening bo'limlari uchun OpenAI TTS orqali audio generatsiya qilish */
    public function generateAudio(TestTemplate $test): JsonResponse
    {
        abort_unless(Feature::audio(), 422, 'Audio (tinglash) funksiyasi o\'chirilgan.');

        $count = $this->tests->generateAudio($test, (bool) request()->boolean('force'));

        return $this->ok(new TestTemplateResource($this->tests->withQuestions($test->fresh())), "{$count} ta audio yaratildi.");
    }

    /** Nashr qilish / yashirish */
    public function publish(TestTemplate $test): JsonResponse
    {
        return $this->updated(new TestTemplateResource($this->tests->publish($test, request()->boolean('published', true))), 'Holat yangilandi.');
    }

    /** Ochiq va ovozli javoblarni qo'lda baholash */
    public function gradeAttempt(GradeAttemptRequest $request, TestAttempt $attempt): JsonResponse
    {
        return $this->updated(
            new TestAttemptResource($this->tests->grade($attempt, $request->validated()['scores'])->load('answers.question')),
            'Test baholandi.'
        );
    }
}
