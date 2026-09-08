<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    protected UserRepository $userRepository;
    public function __construct(
        UserRepository $userRepository,
    ) {
        $this->userRepository = $userRepository;
    }

    public function login(string $username, string $password): array
    {
        $user = $this->userRepository->findByUserName($username);

        if (!$user || !Hash::check($password, $user->password)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                response()->json(['message' => 'The provided credentials are incorrect.'], 401)
            );
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
