<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\FinanceService;
use App\Services\NotificationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hisob-fakturalar va to'lovlar. Uch xil holat ataylab yaratiladi:
 *  1) To'liq to'lagan — balans 0
 *  2) Ortiqcha to'lagan — musbat balans keyingi oyga o'tadi
 *  3) Kam yoki umuman to'lamagan — manfiy balans (qarzdorlik)
 */
class FinanceSeeder extends Seeder
{
    public function run(FinanceService $finance, NotificationService $notifications): void
    {
        mt_srand(31337);

        $accountant = User::where('username', 'buxgalter')->first();

        // O'tgan o'quv yilining bahorgi oylari + joriy oy
        $periods = [
            [now()->year, 3],
            [now()->year, 4],
            [now()->year, 5],
            [now()->year, now()->month],
        ];

        $students = Student::orderBy('id')->get();
        $invoiceCount = 0;
        $paymentCount = 0;

        DB::transaction(function () use ($finance, $students, $periods, $accountant, &$invoiceCount, &$paymentCount) {
            foreach ($periods as [$year, $month]) {
                foreach ($students as $index => $student) {
                    $invoice = $finance->generateInvoice($student, $year, $month);

                    if (! $invoice) {
                        continue;
                    }

                    $invoiceCount++;

                    // To'lov xulq-atvori: har 10 o'quvchidan biri to'lamaydi
                    $behaviour = ($index + $month) % 10;

                    if ($behaviour === 0) {
                        continue; // to'lamadi → qarzdorlik
                    }

                    $total = (float) $invoice->total_amount;

                    $amount = match (true) {
                        $behaviour <= 5 => $total,                         // aniq summa
                        $behaviour <= 7 => round($total * 1.15, -3),       // ortiqcha → keyingi oyga o'tadi
                        default => round($total * 0.6, -3),                // qisman → qoldiq qarz
                    };

                    $paidAt = Carbon::create($year, $month, min(28, 3 + (($index * 3) % 20)), 10, 30);

                    $finance->registerPayment($student, [
                        'invoice_id' => $invoice->id,
                        'amount' => $amount,
                        'method' => ['cash', 'card', 'transfer'][($index + $month) % 3],
                        'paid_at' => $paidAt,
                        'receipt_path' => $behaviour % 2 === 0
                            ? sprintf('receipts/%04d-%02d/chek-%05d.jpg', $year, $month, $student->id)
                            : null,
                        'note' => $behaviour > 7 ? 'Qisman to\'lov, qolgani keyingi oyda' : null,
                        'accepted_by' => $accountant?->id,
                    ]);

                    $paymentCount++;
                }
            }
        });

        // Qarzdorlarga eslatma hodisasi (Telegram bot uchun stub)
        $debtors = 0;

        foreach ($students as $student) {
            $balance = $finance->recalculate($student);

            if ($balance->isDebtor()) {
                $notifications->debtReminder($student, (float) $balance->balance);
                $debtors++;
            }
        }

        $paid = Invoice::where('status', Invoice::STATUS_PAID)->count();
        $total = number_format((float) Payment::sum('amount'), 0, '.', ' ');

        $this->command?->info("✅ {$invoiceCount} ta hisob-faktura, {$paymentCount} ta to'lov ({$total} so'm).");
        $this->command?->line("   • To'liq to'langan hisob-fakturalar: {$paid}");
        $this->command?->line("   • Qarzdor o'quvchilar: {$debtors}");
    }
}
