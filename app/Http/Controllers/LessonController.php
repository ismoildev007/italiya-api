<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lesson\IndexLessonRequest;
use App\Http\Requests\Lesson\StoreLessonRequest;
use App\Http\Requests\Lesson\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Services\LessonService;
use Illuminate\Http\JsonResponse;

class LessonController extends Controller
{
    public function __construct(
        private readonly LessonService $lessons,
    ) {}

    public function index(IndexLessonRequest $request): JsonResponse
    {
        return $this->ok(LessonResource::collection(
            $this->lessons->paginate($request->filters(), $request->perPage(50))
        ));
    }

    public function store(StoreLessonRequest $request): JsonResponse
    {
        return $this->created(new LessonResource($this->lessons->create($request->validated())), 'Dars qo\'shildi.');
    }

    public function show(Lesson $lesson): JsonResponse
    {
        return $this->ok(new LessonResource($this->lessons->withDetails($lesson)));
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        return $this->updated(
            new LessonResource($this->lessons->update($lesson, $request->validated())),
            'Dars yangilandi.'
        );
    }

    public function destroy(Lesson $lesson): JsonResponse
    {
        $this->lessons->delete($lesson);

        return $this->deleted('Dars o\'chirildi.');
    }
}
