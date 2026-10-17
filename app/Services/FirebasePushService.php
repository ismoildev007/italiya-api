<?php

namespace App\Services;

use App\Models\PushToken;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Firebase Cloud Messaging (HTTP v1) orqali brauzer push-bildirishnomalari.
 * Sozlash: FIREBASE_CREDENTIALS=storage/app/firebase.json (service account), FIREBASE_PROJECT_ID.
 * Sozlanmagan bo'lsa jimgina o'tkazib yuboradi — tizim buzilmaydi.
 */
class FirebasePushService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.firebase.project_id') && is_file($this->credentialsPath());
    }

    /** Foydalanuvchining barcha qurilmalariga; yuborilganlar sonini qaytaradi. */
    public function sendToUser(?int $userId, string $title, string $body, array $data = [], ?string $url = null): int
    {
        if (! $userId || ! $this->isConfigured()) {
            return 0;
        }

        $tokens = PushToken::where('user_id', $userId)->pluck('token', 'id');
        $sent = 0;

        foreach ($tokens as $id => $token) {
            $result = $this->send($token, $title, $body, $data, $url);

            if ($result === 'invalid') {
                PushToken::whereKey($id)->delete();
            } elseif ($result === 'ok') {
                PushToken::whereKey($id)->update(['last_used_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    public function sendToStudent(Student $student, string $title, string $body, array $data = [], ?string $url = null): int
    {
        return $this->sendToUser($student->user_id, $title, $body, $data, $url);
    }

    /** @return 'ok'|'invalid'|'error'|'skipped' */
    public function send(string $token, string $title, string $body, array $data = [], ?string $url = null): string
    {
        if (! $this->isConfigured()) {
            return 'skipped';
        }

        try {
            $accessToken = $this->accessToken();
            $project = config('services.firebase.project_id');

            $response = Http::withToken($accessToken)->timeout(15)->post(
                "https://fcm.googleapis.com/v1/projects/{$project}/messages:send",
                ['message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_map('strval', $data + ['url' => $url ?? '/']),
                    'webpush' => array_filter([
                        'notification' => ['icon' => '/favicon.ico', 'badge' => '/favicon.ico'],
                        'fcm_options' => $url ? ['link' => $url] : null,
                    ]),
                ]],
            );

            if ($response->successful()) {
                return 'ok';
            }

            $error = (string) $response->json('error.status', '');

            if (in_array($error, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true) || $response->status() === 404) {
                return 'invalid';
            }

            report(new \RuntimeException('FCM xatosi: '.$response->status().' '.$response->body()));

            return 'error';
        } catch (Throwable $e) {
            report($e);

            return 'error';
        }
    }

    /** Service account JWT → OAuth2 access token (1 soatga keshlanadi). */
    private function accessToken(): string
    {
        return Cache::remember('fcm_access_token', 3300, function () {
            $sa = json_decode((string) file_get_contents($this->credentialsPath()), true);
            $now = time();
            $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->b64(json_encode([
                'iss' => $sa['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            openssl_sign("{$header}.{$claims}", $signature, $sa['private_key'], OPENSSL_ALGO_SHA256);
            $jwt = "{$header}.{$claims}.".$this->b64($signature);

            $response = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            abort_unless($response->successful(), 502, 'Firebase token olinmadi.');

            return (string) $response->json('access_token');
        });
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function credentialsPath(): string
    {
        $path = (string) config('services.firebase.credentials');

        return str_starts_with($path, '/') ? $path : base_path($path);
    }
}
