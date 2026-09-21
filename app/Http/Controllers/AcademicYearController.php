<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcademicYear\StoreAcademicYearRequest;
use App\Http\Requests\AcademicYear\UpdateAcademicYearRequest;
use App\Http\Resources\AcademicYearResource;
use App\Models\AcademicYear;
use App\Services\AcademicYearService;
use Illuminate\Http\JsonResponse;

class AcademicYearController extends Controller
{
    public function __construct(
        private readonly AcademicYearService $years,
    ) {}

    public function index(): JsonResponse
    {
        return $this->ok(AcademicYearResource::collection($this->years->list()));
    }

    public function store(StoreAcademicYearRequest $request): JsonResponse
    {
        return $this->created(
            new AcademicYearResource($this->years->create($request->validated())),
            'O\'quv yili yaratildi.'
        );
    }

    public function show(AcademicYear $academicYear): JsonResponse
    {
        return $this->ok(new AcademicYearResource($academicYear->loadCount('classes')));
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): JsonResponse
    {
        return $this->updated(
            new AcademicYearResource($this->years->update($academicYear, $request->validated())),
            'O\'quv yili yangilandi.'
        );
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        $this->years->delete($academicYear);

        return $this->deleted('O\'quv yili o\'chirildi.');
    }

    /** Joriy o'quv yilini belgilash */
    public function activate(AcademicYear $academicYear): JsonResponse
    {
        return $this->updated(
            new AcademicYearResource($this->years->activate($academicYear)),
            'Joriy o\'quv yili o\'zgartirildi.'
        );
    }
}
