<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\Level;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    /**
     * Login — username yoki telefon raqami orqali.
     */
    public function login(string $login, string $password, ?string $device = null, ?string $deviceId = null): array
    {
        $user = $this->users->findByLogin($this->normalizeLogin($login));

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Login yoki parol noto\'g\'ri.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'login' => ['Akkaunt bloklangan. Administratorga murojaat qiling.'],
            ]);
        }

        $student = $user->hasRole('student') ? $user->student()->with('schoolClass')->first() : null;

        if ($student) {
            $this->bindDevice($student, $deviceId);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $token = $user->createToken($device ?: 'api')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('roles'),
            'permissions' => $user->isSuperadmin() ? ['*'] : $user->permissionNames()->all(),
            'student' => $student ? $this->studentSummary($student) : null,
        ];
    }

    /**
     * O'quvchi akkaunti bitta qurilmaga bog'lanadi (mockupdagi device binding, server tomonda).
     * Birinchi kirishda qurilma bog'lanadi; boshqa qurilmadan kirish rad etiladi —
     * administrator `students/{id}/device` orqali tozalaydi.
     */
    private function bindDevice(Student $student, ?string $deviceId): void
    {
        if (! $deviceId) {
            return;
        }

        if (! $student->device_id) {
            $student->forceFill(['device_id' => $deviceId, 'device_bound_at' => now()])->saveQuietly();

            return;
        }

        if ($student->device_id !== $deviceId) {
            throw ValidationException::withMessages([
                'device_id' => ['Bu hisob boshqa qurilmaga bog\'langan. Administratorga murojaat qiling.'],
            ]);
        }
    }

    private function studentSummary(Student $student): array
    {
        return [
            'id' => $student->id,
            'full_name' => $student->full_name,
            'level' => $student->effectiveLevel(),
            'level_title' => Level::title($student->effectiveLevel()),
            'track' => $student->effectiveTrack(),
            'group' => $student->schoolClass?->name,
            'group_id' => $student->class_id,
            'preferred_language' => $student->translationLanguage(),
        ];
    }

    /** +998 90 123-45-67 → +998901234567 (telefon orqali login uchun) */
    private function normalizeLogin(string $login): string
    {
        $digits = preg_replace('/\D+/', '', $login) ?? '';

        return strlen($digits) >= 9 && preg_match('/^[\d\s\-\+\(\)]+$/', $login) ? '+'.$digits : $login;
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * @param  array{current_password: string, password: string}  $data
     */
    public function changePassword(User $user, array $data): void
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Joriy parol noto\'g\'ri.'],
            ]);
        }

        $user->forceFill(['password' => $data['password']])->save();
    }

    public function profile(User $user): array
    {
        return [
            'user' => $user->load('roles'),
            'permissions' => $user->isSuperadmin() ? ['*'] : $user->permissionNames()->all(),
            'school' => [
                'id' => config('school.id'),
                'name' => config('school.name'),
                'short_name' => config('school.short_name'),
                'logo' => config('school.logo'),
                'phone' => config('school.phone'),
                'address' => config('school.address'),
                'currency' => config('school.currency'),
                'website' => config('school.website') ?: null,
                'director' => config('school.director') ?: null,
                'telegram' => config('school.telegram') ?: null,
            ],
            'academy' => [
                'features' => config('academy.features'),
                'xp' => config('academy.xp'),
                'target_language' => config('academy.target_language'),
                'levels' => config('academy.levels'),
                'skills' => config('academy.skills'),
                'tracks' => config('academy.tracks'),
                'translation_languages' => config('academy.translation_languages'),
            ],
            'student' => $user->hasRole('student') ? ($user->student()->with('schoolClass')->first()?->only(['id', 'level', 'track', 'class_id', 'preferred_language'])) : null,
        ];
    }
}
