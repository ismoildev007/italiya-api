<?php

namespace App\Http\Controllers;

use App\Http\Requests\Learn\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Foydalanuvchi sozlamalari (mockupdagi settings modal): bildirishnomalar, ovoz,
 * mavzu, interfeys tili; o'quvchi uchun kartochka tarjima tili.
 */
class SettingsController extends Controller
{
    private const DEFAULTS = ['notifications' => true, 'sound' => true, 'theme' => 'auto', 'ui_language' => 'uz'];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            ...self::DEFAULTS,
            ...($user->settings ?? []),
            'preferred_language' => $user->student?->preferred_language,
            'options' => [
                'themes' => ['auto', 'light', 'dark'],
                'languages' => config('academy.translation_languages'),
            ],
        ]);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $user->update(['settings' => [...self::DEFAULTS, ...($user->settings ?? []), ...collect($data)->except('preferred_language')->all()]]);

        if (! empty($data['preferred_language']) && $user->student) {
            $user->student->update(['preferred_language' => $data['preferred_language']]);
        }

        return $this->updated([...$user->fresh()->settings, 'preferred_language' => $user->student?->fresh()->preferred_language], 'Sozlamalar saqlandi.');
    }
}
