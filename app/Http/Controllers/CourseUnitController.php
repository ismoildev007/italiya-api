<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourseUnit\GenerateUnitRequest;
use App\Http\Requests\CourseUnit\IndexUnitRequest;
use App\Http\Requests\CourseUnit\StoreUnitRequest;
use App\Http\Requests\CourseUnit\UpdateUnitRequest;
use App\Http\Resources\CourseUnitResource;
use App\Models\CourseUnit;
use App\Services\CourseUnitService;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;

/**
 * Darsliklar (A1..C2 × yo'nalish × ko'nikma): AI generatsiya → o'qituvchi tahriri → nashr.
 */
class CourseUnitController extends Controller
{
    public function __construct(private readonly CourseUnitService $units) {}

    public function index(IndexUnitRequest $request): JsonResponse
    {
        return $this->ok(CourseUnitResource::collection($this->units->paginate($request->filters(), $request->perPage())));
    }

    /** AI orqali dars mazmuni (bazaga yozilmaydi) */
    public function generate(GenerateUnitRequest $request): JsonResponse
    {
        abort_unless(Feature::ai(), 422, 'AI funksiyasi o\'chirilgan. Yoqish uchun .env da AI_ENABLED=true.');

        return $this->ok($this->units->generate($request->validated()), 'Dars generatsiya qilindi.');
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        return $this->created(new CourseUnitResource($this->units->save($request->validated() + ['media' => $request->file('media')])), 'Darslik saqlandi.');
    }

    public function show(CourseUnit $unit): JsonResponse
    {
        return $this->ok(new CourseUnitResource($unit->load(['teacher.user', 'practiceTest.sections', 'materials'])));
    }

    public function update(UpdateUnitRequest $request, CourseUnit $unit): JsonResponse
    {
        return $this->updated(new CourseUnitResource($this->units->save($request->validated() + ['media' => $request->file('media')], $unit)), 'Darslik yangilandi.');
    }

    public function destroy(CourseUnit $unit): JsonResponse
    {
        $this->units->delete($unit);

        return $this->deleted('Darslik o\'chirildi.');
    }

    /** media_script → OpenAI TTS audio */
    public function generateAudio(CourseUnit $unit): JsonResponse
    {
        abort_unless(Feature::audio(), 422, 'Audio (tinglash) funksiyasi o\'chirilgan.');

        $path = $this->units->generateAudio($unit, request()->boolean('force'));

        abort_unless($path, 422, 'Audio yaratilmadi: media_script bo\'sh yoki OPENAI_API_KEY sozlanmagan.');

        return $this->ok(new CourseUnitResource($unit->fresh(['teacher.user', 'practiceTest', 'materials'])), 'Audio yaratildi.');
    }
}
