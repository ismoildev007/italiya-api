<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function index(): JsonResponse
    {
        return response()->json($this->userService->getAll());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($this->userService->getOne($user));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
        ]);

        return response()->json($this->userService->update($user, $validated));
    }

    public function destroy(User $user): JsonResponse
    {
        $this->userService->delete($user);

        return response()->json(null, 204);
    }
}
