<?php

namespace App\Http\Controllers;

use App\Http\Requests\Subject\IndexSubjectRequest;
use App\Http\Requests\Subject\StoreSubjectRequest;
use App\Http\Requests\Subject\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\Subject;
use App\Services\SubjectService;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    public function __construct(
        private readonly SubjectService $subjects,
    ) {}

    public function index(IndexSubjectRequest $request): JsonResponse
    {
        return $this->ok(SubjectResource::collection($this->subjects->list($request->filters())));
    }

    public function store(StoreSubjectRequest $request): JsonResponse
    {
        return $this->created(new SubjectResource($this->subjects->create($request->validated())), 'Fan yaratildi.');
    }

    public function show(Subject $subject): JsonResponse
    {
        return $this->ok(new SubjectResource($subject));
    }

    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        return $this->updated(
            new SubjectResource($this->subjects->update($subject, $request->validated())),
            'Fan yangilandi.'
        );
    }

    public function destroy(Subject $subject): JsonResponse
    {
        $this->subjects->delete($subject);

        return $this->deleted('Fan o\'chirildi.');
    }
}
