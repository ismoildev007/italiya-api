<?php

namespace App\Http\Controllers;

use App\Http\Requests\SchoolClass\AssignClassTeacherRequest;
use App\Http\Requests\SchoolClass\AssignTeachersRequest;
use App\Http\Requests\SchoolClass\IndexSchoolClassRequest;
use App\Http\Requests\SchoolClass\StoreSchoolClassRequest;
use App\Http\Requests\SchoolClass\UpdateSchoolClassRequest;
use App\Http\Resources\ClassSubjectTeacherResource;
use App\Http\Resources\SchoolClassResource;
use App\Http\Resources\StudentResource;
use App\Models\SchoolClass;
use App\Services\SchoolClassService;
use Illuminate\Http\JsonResponse;

class SchoolClassController extends Controller
{
    public function __construct(
        private readonly SchoolClassService $classes,
    ) {}

    public function index(IndexSchoolClassRequest $request): JsonResponse
    {
        return $this->ok(SchoolClassResource::collection($this->classes->list($request->filters())));
    }

    public function store(StoreSchoolClassRequest $request): JsonResponse
    {
        return $this->created(
            new SchoolClassResource($this->classes->create($request->validated())),
            'Sinf yaratildi.'
        );
    }

    public function show(SchoolClass $schoolClass): JsonResponse
    {
        return $this->ok(new SchoolClassResource($this->classes->withDetails($schoolClass)));
    }

    public function update(UpdateSchoolClassRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        return $this->updated(
            new SchoolClassResource($this->classes->update($schoolClass, $request->validated())),
            'Sinf yangilandi.'
        );
    }

    public function destroy(SchoolClass $schoolClass): JsonResponse
    {
        $this->classes->delete($schoolClass);

        return $this->deleted('Sinf o\'chirildi.');
    }

    /** Sinf o'quvchilari ro'yxati */
    public function students(SchoolClass $schoolClass): JsonResponse
    {
        return $this->ok(StudentResource::collection($this->classes->students($schoolClass)));
    }

    /** Sinfga fan o'qituvchilarini biriktirish */
    public function assignTeachers(AssignTeachersRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        return $this->updated(
            ClassSubjectTeacherResource::collection(
                $this->classes->assignTeachers($schoolClass, $request->validated()['assignments'])
            ),
            'O\'qituvchilar biriktirildi.'
        );
    }

    /** Sinf rahbarini tayinlash */
    public function assignClassTeacher(AssignClassTeacherRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        return $this->updated(
            new SchoolClassResource(
                $this->classes->assignClassTeacher($schoolClass, $request->validated()['teacher_id'])
            ),
            'Sinf rahbari tayinlandi.'
        );
    }
}
