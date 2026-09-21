<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    ) {}

    /** Login — username yoki telefon + parol. */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->auth->login($data['login'], $data['password'], $data['device'] ?? null, $data['device_id'] ?? null);

        return $this->ok([
            ...$result,
            'user' => new UserResource($result['user']),
        ], 'Tizimga muvaffaqiyatli kirdingiz.');
    }

    /** Joriy foydalanuvchi, uning ruxsatlari va maktab ma'lumotlari. */
    public function me(Request $request): JsonResponse
    {
        $profile = $this->auth->profile($request->user());

        return $this->ok([
            ...$profile,
            'user' => new UserResource($profile['user']),
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->auth->changePassword($request->user(), $request->payload(['password_confirmation']));

        return $this->ok(null, 'Parol yangilandi.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return $this->ok(null, 'Tizimdan chiqdingiz.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->auth->logoutAll($request->user());

        return $this->ok(null, 'Barcha qurilmalardan chiqildi.');
    }
}
