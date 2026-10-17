<?php

namespace App\Http\Controllers;

use App\Models\PushToken;
use App\Services\FirebasePushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Brauzer push-token'larini ro'yxatga olish (Firebase). */
class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:512', 'platform' => 'nullable|in:web,android,ios']);

        PushToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'token' => $data['token']],
            ['platform' => $data['platform'] ?? 'web', 'user_agent' => substr((string) $request->userAgent(), 0, 255), 'last_used_at' => now()],
        );

        return $this->ok(['registered' => true], 'Push token saqlandi.');
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => 'required|string|max:512']);
        PushToken::where('user_id', $request->user()->id)->where('token', $data['token'])->delete();

        return $this->deleted('Push token o\'chirildi.');
    }

    /** Test xabari — foydalanuvchi o'ziga yuboradi (sozlamani tekshirish uchun) */
    public function test(Request $request, FirebasePushService $push): JsonResponse
    {
        abort_unless($push->isConfigured(), 422, 'Firebase sozlanmagan (FIREBASE_CREDENTIALS, FIREBASE_PROJECT_ID).');
        $sent = $push->sendToUser($request->user()->id, 'Italiya Akademiyasi', 'Push-bildirishnomalar ishlayapti ✅', ['type' => 'test']);

        return $this->ok(['sent' => $sent], "{$sent} ta qurilmaga yuborildi.");
    }

    public function status(FirebasePushService $push): JsonResponse
    {
        return $this->ok(['configured' => $push->isConfigured()]);
    }
}
