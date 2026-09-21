<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\IndexRoleRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\SyncPermissionsRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roles,
    ) {}

    public function index(IndexRoleRequest $request): JsonResponse
    {
        return $this->ok(RoleResource::collection($this->roles->search($request->filters())));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return $this->created(new RoleResource($this->roles->create($request->validated())), 'Rol yaratildi.');
    }

    public function show(Role $role): JsonResponse
    {
        return $this->ok(new RoleResource($this->roles->withDetails($role)));
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        return $this->updated(new RoleResource($this->roles->update($role, $request->validated())), 'Rol yangilandi.');
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->roles->delete($role);

        return $this->deleted('Rol o\'chirildi.');
    }

    /** Rolga biriktirilgan permission'lar */
    public function permissions(Role $role): JsonResponse
    {
        return $this->ok($this->roles->permissionMatrix($role));
    }

    /** Rolga permission'larni biriktirish (to'liq almashtirish) */
    public function syncPermissions(SyncPermissionsRequest $request, Role $role): JsonResponse
    {
        $count = $this->roles->syncPermissions($role, $request->validated()['permissions']);

        return $this->updated(['assigned' => $count], "{$count} ta ruxsat biriktirildi.");
    }
}
