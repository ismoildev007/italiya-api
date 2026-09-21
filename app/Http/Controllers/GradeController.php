<?php

namespace App\Http\Controllers;

use App\Http\Requests\Grade\BulkGradeRequest;
use App\Http\Requests\Grade\IndexGradeRequest;
use App\Http\Requests\Grade\StoreGradeRequest;
use App\Http\Requests\Grade\UpdateGradeRequest;
use App\Http\Resources\GradeResource;
use App\Models\Grade;
use App\Services\GradeService;
use Illuminate\Http\JsonResponse;

class GradeController extends Controller
{
    public function __construct(
        private readonly GradeService $grades,
    ) {}

    public function index(IndexGradeRequest $request): JsonResponse
    {
        return $this->ok(GradeResource::collection(
            $this->grades->paginate($request->filters(), $request->perPage(50))
        ));
    }

    public function store(StoreGradeRequest $request): JsonResponse
    {
        return $this->created(
            new GradeResource($this->grades->create($request->validated())->load(['student', 'subject'])),
            'Baho qo\'yildi.'
        );
    }

    /** Sinf uchun to'plamli baholash (jurnal ko'rinishi) */
    public function bulkStore(BulkGradeRequest $request): JsonResponse
    {
        $count = $this->grades->createBulk($request->validated());

        return $this->created(['saved' => $count], "{$count} ta baho qo'yildi.");
    }

    public function show(Grade $grade): JsonResponse
    {
        return $this->ok(new GradeResource($this->grades->withDetails($grade)));
    }

    public function update(UpdateGradeRequest $request, Grade $grade): JsonResponse
    {
        return $this->updated(
            new GradeResource($this->grades->update($grade, $request->validated())),
            'Baho yangilandi.'
        );
    }

    public function destroy(Grade $grade): JsonResponse
    {
        $this->grades->delete($grade);

        return $this->deleted('Baho o\'chirildi.');
    }
}
