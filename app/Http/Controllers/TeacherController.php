<?php

namespace App\Http\Controllers;

use App\Http\Requests\Teacher\IndexTeacherRequest;
use App\Http\Requests\Teacher\StoreTeacherRequest;
use App\Http\Requests\Teacher\UpdateSalarySettingsRequest;
use App\Http\Requests\Teacher\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher;
use App\Services\TeacherService;
use Illuminate\Http\JsonResponse;

class TeacherController extends Controller
{
    public function __construct(
        private readonly TeacherService $teachers,
    ) {}

    public function index(IndexTeacherRequest $request): JsonResponse
    {
        return $this->ok(TeacherResource::collection(
            $this->teachers->paginate($request->filters(), $request->perPage())
        ));
    }

    public function store(StoreTeacherRequest $request): JsonResponse
    {
        return $this->created(
            new TeacherResource($this->teachers->create($request->validated())->load('user')),
            'O\'qituvchi qo\'shildi.'
        );
    }

    public function show(Teacher $teacher): JsonResponse
    {
        return $this->ok(new TeacherResource(
            $teacher->load(['user.roles', 'supervisedClasses', 'assignments.subject', 'assignments.schoolClass'])
        ));
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher): JsonResponse
    {
        return $this->updated(
            new TeacherResource($this->teachers->update($teacher, $request->validated())),
            'O\'qituvchi ma\'lumotlari yangilandi.'
        );
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        $this->teachers->delete($teacher);

        return $this->deleted('O\'qituvchi arxivlandi.');
    }

    /** Ish haqi rejimini o'zgartirish (fiksed / soatbay) */
    public function updateSalarySettings(UpdateSalarySettingsRequest $request, Teacher $teacher): JsonResponse
    {
        return $this->updated(
            new TeacherResource($this->teachers->updateSalarySettings($teacher, $request->validated())),
            'Ish haqi rejimi yangilandi.'
        );
    }
}
