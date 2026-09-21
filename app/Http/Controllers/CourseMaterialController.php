<?php

namespace App\Http\Controllers;

use App\Http\Requests\CourseUnit\IndexMaterialRequest;
use App\Http\Requests\CourseUnit\StoreMaterialRequest;
use App\Http\Resources\CourseMaterialResource;
use App\Models\CourseMaterial;
use App\Services\CourseUnitService;
use Illuminate\Http\JsonResponse;

/** Guruh kutubxonasi: video darsliklar, kitoblar, fayllar. */
class CourseMaterialController extends Controller
{
    public function __construct(private readonly CourseUnitService $units) {}

    public function index(IndexMaterialRequest $request): JsonResponse
    {
        return $this->ok(CourseMaterialResource::collection($this->units->materials($request->filters())));
    }

    public function store(StoreMaterialRequest $request): JsonResponse
    {
        return $this->created(new CourseMaterialResource($this->units->addMaterial($request->validated(), $request->file('file'))), 'Material yuklandi.');
    }

    public function destroy(CourseMaterial $material): JsonResponse
    {
        $this->units->deleteMaterial($material);

        return $this->deleted('Material o\'chirildi.');
    }
}
