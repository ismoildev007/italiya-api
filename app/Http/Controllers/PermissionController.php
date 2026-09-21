<?php

namespace App\Http\Controllers;

use App\Http\Requests\Permission\IndexPermissionRequest;
use App\Http\Requests\Permission\SyncPermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;

class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionService $permissions,
    ) {}

    public function index(IndexPermissionRequest $request): JsonResponse
    {
        return $this->ok(PermissionResource::collection($this->permissions->search($request->filters())));
    }

    /** Guruhlangan ko'rinish — admin panelda checkbox daraxti uchun */
    public function grouped(): JsonResponse
    {
        return $this->ok($this->permissions->grouped());
    }

    /** Route'lardan permission'larni qayta generatsiya qilish */
    public function sync(SyncPermissionRequest $request): JsonResponse
    {
        return $this->ok(
            $this->permissions->syncFromRoutes($request->shouldPrune()),
            'Ruxsatlar route\'lar bo\'yicha yangilandi.'
        );
    }
}
