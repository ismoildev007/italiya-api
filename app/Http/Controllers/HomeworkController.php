<?php

namespace App\Http\Controllers;

use App\Http\Requests\Homework\GradeSubmissionRequest;
use App\Http\Requests\Homework\IndexHomeworkRequest;
use App\Http\Requests\Homework\StoreHomeworkRequest;
use App\Http\Requests\Homework\UpdateHomeworkRequest;
use App\Http\Resources\HomeworkResource;
use App\Http\Resources\HomeworkSubmissionResource;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Services\HomeworkService;
use Illuminate\Http\JsonResponse;

/** Uy ishi: berish, topshiriqlarni ko'rish, baholash (0..100). */
class HomeworkController extends Controller
{
    public function __construct(private readonly HomeworkService $homework) {}

    public function index(IndexHomeworkRequest $request): JsonResponse
    {
        return $this->ok(HomeworkResource::collection($this->homework->paginate($request->filters(), $request->perPage())));
    }

    public function store(StoreHomeworkRequest $request): JsonResponse
    {
        return $this->created(new HomeworkResource($this->homework->save($request->validated(), null, $request->file('attachment'))), 'Uy ishi berildi.');
    }

    public function show(Homework $homework): JsonResponse
    {
        return $this->ok(new HomeworkResource($homework->load(['teacher.user', 'schoolClass', 'student', 'testTemplate', 'unit', 'submissions.student'])));
    }

    public function update(UpdateHomeworkRequest $request, Homework $homework): JsonResponse
    {
        return $this->updated(new HomeworkResource($this->homework->save($request->validated(), $homework, $request->file('attachment'))), 'Uy ishi yangilandi.');
    }

    public function destroy(Homework $homework): JsonResponse
    {
        $this->homework->delete($homework);

        return $this->deleted('Uy ishi o\'chirildi.');
    }

    public function submissions(Homework $homework): JsonResponse
    {
        return $this->ok(HomeworkSubmissionResource::collection($this->homework->submissions($homework)));
    }

    public function grade(GradeSubmissionRequest $request, HomeworkSubmission $submission): JsonResponse
    {
        $data = $request->validated();

        return $this->updated(
            new HomeworkSubmissionResource($this->homework->grade($submission, (float) $data['score'], $data['feedback'] ?? null, (bool) ($data['returned'] ?? false))),
            'Uy ishi baholandi.'
        );
    }
}
