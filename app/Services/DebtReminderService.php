<?php

namespace App\Services;

use App\Models\StudentBalance;

/**
 * Qarzdor o'quvchilarning ota-onalariga Telegram orqali eslatma
 * (config: academy.debt_reminder_days kunlari, yoki qo'lda).
 */
class DebtReminderService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** Yaratilgan hodisalar sonini qaytaradi. */
    public function remindAll(?float $minDebt = null): int
    {
        $count = 0;

        StudentBalance::with('student')
            ->where('balance', '<', 0)
            ->when($minDebt, fn ($q) => $q->where('balance', '<=', -$minDebt))
            ->chunkById(200, function ($balances) use (&$count) {
                foreach ($balances as $balance) {
                    if ($balance->student && $balance->student->status === 'active') {
                        $this->notifications->debtReminder($balance->student, (float) $balance->balance);
                        $count++;
                    }
                }
            });

        return $count;
    }
}
