<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\IndexUserRequest;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\SyncRolesRequest;
use App\Http\Requests\User\UpdateStatusRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {}

    public function index(IndexUserRequest $request): JsonResponse
    {
        return $this->ok(UserResource::collection(
            $this->users->paginate($request->filters(), $request->perPage())
        ));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        return $this->created(
            new UserResource($this->users->create($request->validated())),
            'Foydalanuvchi yaratildi.'
        );
    }

    public function show(User $user): JsonResponse
    {
        return $this->ok(new UserResource($user->load('roles')));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return $this->updated(
            new UserResource($this->users->update($user, $request->validated())),
            'Foydalanuvchi yangilandi.'
        );
    }

    public function destroy(User $user): JsonResponse
    {
        $this->users->delete($user);

        return $this->deleted('Foydalanuvchi o\'chirildi.');
    }

    /** Akkauntni bloklash / blokdan chiqarish */
    public function toggleStatus(UpdateStatusRequest $request, User $user): JsonResponse
    {
        return $this->updated(
            new UserResource($this->users->setStatus($user, $request->validated()['status'])->load('roles')),
            'Holat yangilandi.'
        );
    }

    /** Administrator tomonidan parolni tiklash */
    public function resetPassword(ResetPasswordRequest $request, User $user): JsonResponse
    {
        $this->users->resetPassword($user, $request->validated()['password']);

        return $this->updated(null, 'Parol tiklandi.');
    }

    /** Foydalanuvchi rollarini almashtirish */
    public function syncRoles(SyncRolesRequest $request, User $user): JsonResponse
    {
        $this->users->syncRoles($user, $request->validated()['roles']);

        return $this->updated(new UserResource($user->load('roles')), 'Rollar yangilandi.');
    }
}
