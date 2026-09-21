<?php

namespace App\Services\Telegram;

use App\Models\Guardian;
use App\Models\MonthlyReport;
use App\Models\TelegramSession;
use App\Services\MonthlyReportService;
use Throwable;

/**
 * Ota-onalar boti: telefon/kod orqali bog'lanish, qarzdorlik, oylik hisobot, farzandlar.
 * Qarzdorlik va oylik tahlil xabarlari `notification_events` orqali avtomatik keladi.
 */
class ParentBotService
{
    public function __construct(private readonly MonthlyReportService $reports) {}

    public function handle(array $update): void
    {
        $message = $update['message'] ?? null;

        if (! $message) {
            return;
        }

        $chatId = (string) $message['chat']['id'];
        $text = trim((string) ($message['text'] ?? ''));
        $contact = $message['contact']['phone_number'] ?? null;

        try {
            $session = TelegramSession::firstOrCreate(['bot' => 'parent', 'chat_id' => $chatId], ['state' => TelegramSession::STATE_IDLE]);
            $guardian = $session->guardian_id ? Guardian::find($session->guardian_id) : Guardian::where('telegram_chat_id', $chatId)->first();

            if ($text === '/start' || (! $guardian && $session->state === TelegramSession::STATE_IDLE)) {
                $session->setState(TelegramSession::STATE_AWAIT_PHONE);
                $this->reply($chatId, "👋 <b>Italiya Akademiyasi</b> — ota-onalar boti.\n\nFarzandingiz haqida xabarlar (to'lov, qarzdorlik, oylik tahlil, uy ishi) shu yerga keladi.\nBog'lanish uchun telefon raqamingizni yoki bog'lash kodini yuboring.", [
                    'keyboard' => [[['text' => '📱 Raqamni yuborish', 'request_contact' => true]]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ]);

                return;
            }

            if ($session->state === TelegramSession::STATE_AWAIT_PHONE) {
                $phone = $contact ?: $text;
                $normalized = '+'.preg_replace('/\D+/', '', $phone);

                $guardian = Guardian::where('link_code', strtoupper($text))
                    ->orWhere('phone', $phone)
                    ->orWhere('phone', $normalized)
                    ->first();

                if (! $guardian) {
                    $this->reply($chatId, "❌ Bu raqam/kod bo'yicha ota-ona topilmadi. Administratorga murojaat qiling.");

                    return;
                }

                $guardian->update(['telegram_chat_id' => $chatId, 'linked_at' => now()]);
                $session->update(['guardian_id' => $guardian->id, 'phone' => $normalized, 'state' => TelegramSession::STATE_IDLE]);

                $children = $guardian->students()->with('schoolClass')->get()->map(fn ($s) => "• {$s->full_name} — {$s->effectiveLevel()} ({$s->schoolClass?->name})")->implode("\n");
                $this->reply($chatId, "✅ Bog'landi, <b>{$guardian->firstname}</b>!\n\nFarzandlar:\n{$children}\n\nBuyruqlar: /debt — qarzdorlik, /report — oylik hisobot, /children — farzandlar", ['remove_keyboard' => true]);

                return;
            }

            match ($text) {
                '/debt' => $this->debt($chatId, $guardian),
                '/report' => $this->report($chatId, $guardian),
                '/children' => $this->children($chatId, $guardian),
                default => $this->reply($chatId, 'Buyruqlar: /debt, /report, /children'),
            };
        } catch (Throwable $e) {
            report($e);
            $this->reply($chatId, '⚠️ Xatolik: '.e($e->getMessage()));
        }
    }

    private function debt(string $chatId, Guardian $guardian): void
    {
        $lines = $guardian->students()->with('balance')->get()->map(function ($s) {
            $balance = (float) ($s->balance?->balance ?? 0);

            return $balance < 0
                ? sprintf("🔴 %s: qarzdorlik <b>%s so'm</b>", $s->full_name, number_format(abs($balance), 0, '.', ' '))
                : sprintf('🟢 %s: qarz yo\'q (balans %s so\'m)', $s->full_name, number_format($balance, 0, '.', ' '));
        })->implode("\n");

        $this->reply($chatId, "💳 <b>To'lov holati</b>\n\n".($lines ?: 'Farzand biriktirilmagan.'));
    }

    private function report(string $chatId, Guardian $guardian): void
    {
        foreach ($guardian->students as $student) {
            $report = MonthlyReport::where('student_id', $student->id)->orderByDesc('year')->orderByDesc('month')->first()
                ?? $this->reports->build($student, now()->year, now()->month, false);

            $this->reply($chatId, $this->reports->format($report->load('student')));
        }
    }

    private function children(string $chatId, Guardian $guardian): void
    {
        $lines = $guardian->students()->with('schoolClass')->get()
            ->map(fn ($s) => "• {$s->full_name} — daraja {$s->effectiveLevel()}, guruh {$s->schoolClass?->name}, oylik {$s->monthly_hours} soat")
            ->implode("\n");

        $this->reply($chatId, "👨‍👩‍👧 <b>Farzandlar</b>\n\n".($lines ?: '—'));
    }

    private function reply(string $chatId, string $text, ?array $markup = null): void
    {
        TelegramClient::parent()->sendMessage($chatId, $text, $markup);
    }
}
