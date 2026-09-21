<?php

namespace App\Services;

use App\Models\FinanceTransaction;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentBalance;
use App\Models\StudentDiscount;
use App\Models\StudentTariff;
use App\Models\Tariff;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tarif, chegirma, hisob-faktura, to'lov va balans mantig'i.
 *
 * Balans qoidasi:
 *  - To'lov oylik summadan KO'P bo'lsa → farq keyingi oyga musbat balans bo'lib o'tadi
 *  - To'lov KAM yoki umuman bo'lmasa → farq manfiy balans (qarzdorlik) bo'lib ko'rinadi
 *
 * Har bir harakat `finance_transactions` jadvaliga yoziladi — tarix to'liq ochiq.
 */
class FinanceService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    /* ------------------------------------------------------------------ */
    /* Tarif va chegirma */
    /* ------------------------------------------------------------------ */

    public function assignTariff(Student $student, Tariff $tariff, ?CarbonInterface $startsOn = null): StudentTariff
    {
        return DB::transaction(function () use ($student, $tariff, $startsOn) {
            $startsOn = $startsOn ? Carbon::parse($startsOn) : now();

            // Avvalgi tarifni yopamiz — tarix saqlanadi
            $student->tariffs()->where('is_active', true)->update([
                'is_active' => false,
                'ends_on' => $startsOn->copy()->subDay()->toDateString(),
            ]);

            $studentTariff = $student->tariffs()->create([
                'school_id' => config('school.id'),
                'tariff_id' => $tariff->id,
                'starts_on' => $startsOn->toDateString(),
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            $this->log($student, FinanceTransaction::TYPE_TARIFF_CHANGE, 0, $studentTariff,
                "Tarif belgilandi: {$tariff->name} (".number_format((float) $tariff->monthly_price, 0, '.', ' ').' so\'m/oy)');

            return $studentTariff;
        });
    }

    /**
     * Individual chegirma — faqat shu o'quvchiga tegishli.
     */
    public function setDiscount(Student $student, string $type, float $value, ?string $reason = null, ?CarbonInterface $startsOn = null): StudentDiscount
    {
        return DB::transaction(function () use ($student, $type, $value, $reason, $startsOn) {
            $startsOn = $startsOn ? Carbon::parse($startsOn) : now();

            $student->discounts()->where('is_active', true)->update([
                'is_active' => false,
                'ends_on' => $startsOn->copy()->subDay()->toDateString(),
            ]);

            $discount = $student->discounts()->create([
                'school_id' => config('school.id'),
                'type' => $type,
                'value' => $value,
                'reason' => $reason,
                'starts_on' => $startsOn->toDateString(),
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            $label = $type === StudentDiscount::TYPE_PERCENT
                ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'%'
                : number_format($value, 0, '.', ' ').' so\'m';

            $this->log($student, FinanceTransaction::TYPE_DISCOUNT_CHANGE, 0, $discount,
                "Chegirma o'rnatildi: {$label}".($reason ? " — {$reason}" : ''));

            return $discount;
        });
    }

    /**
     * So'rovdan kelgan ma'lumot bo'yicha tarif biriktiradi.
     *
     * @param  array{tariff_id:int, starts_on?:string}  $data
     */
    public function assignTariffFromInput(Student $student, array $data): StudentTariff
    {
        return $this->assignTariff(
            $student,
            Tariff::findOrFail($data['tariff_id']),
            isset($data['starts_on']) ? Carbon::parse($data['starts_on']) : null,
        );
    }

    /**
     * So'rovdan kelgan ma'lumot bo'yicha individual chegirma o'rnatadi.
     *
     * @param  array{type:string, value:float, reason?:string, starts_on?:string}  $data
     */
    public function setDiscountFromInput(Student $student, array $data): StudentDiscount
    {
        return $this->setDiscount(
            $student,
            $data['type'],
            (float) $data['value'],
            $data['reason'] ?? null,
            isset($data['starts_on']) ? Carbon::parse($data['starts_on']) : null,
        );
    }

    /** Amaldagi chegirmani bekor qiladi. */
    public function removeDiscount(Student $student): void
    {
        $student->discounts()->where('is_active', true)->update([
            'is_active' => false,
            'ends_on' => now()->toDateString(),
        ]);

        $this->log($student, FinanceTransaction::TYPE_DISCOUNT_CHANGE, 0, null, 'Chegirma bekor qilindi');
    }

    /** Balans xulosasi — API javoblarida bir xil format. */
    public function balanceSummary(Student $student): array
    {
        $balance = $this->recalculate($student);

        return [
            'balance' => (float) $balance->balance,
            'total_charged' => (float) $balance->total_charged,
            'total_paid' => (float) $balance->total_paid,
            'is_debtor' => $balance->isDebtor(),
            'recalculated_at' => $balance->recalculated_at?->toDateTimeString(),
        ];
    }

    /** Joriy oylik summa: tarif − chegirma. */
    public function monthlyAmountSummary(Student $student): array
    {
        $amounts = $this->monthlyAmount($student);

        return [
            'base' => $amounts['base'],
            'discount' => $amounts['discount'],
            'total' => $amounts['total'],
            'tariff' => $amounts['tariff'] ? [
                'id' => $amounts['tariff']->id,
                'name' => $amounts['tariff']->name,
                'monthly_price' => (float) $amounts['tariff']->monthly_price,
            ] : null,
        ];
    }

    /** O'quvchining moliyaviy tranzaksiyalari. */
    public function transactions(Student $student, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $student->transactions()
            ->with('author')
            ->filter($filters, ['type' => 'type'])
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /* ------------------------------------------------------------------ */
    /* Hisob-fakturalar */
    /* ------------------------------------------------------------------ */

    /**
     * Oylik summani hisoblaydi: tarif narxi − chegirma.
     *
     * @return array{base: float, discount: float, total: float, tariff: ?Tariff}
     */
    public function monthlyAmount(Student $student): array
    {
        $studentTariff = $student->relationLoaded('activeTariff')
            ? $student->activeTariff
            : $student->tariffs()->with('tariff')->where('is_active', true)->latest('id')->first();

        $tariff = $studentTariff?->tariff;
        $base = (float) ($tariff?->monthly_price ?? 0);

        $discountModel = $student->discounts()->where('is_active', true)->latest('id')->first();
        $discount = $discountModel ? $discountModel->amountFor($base) : 0.0;

        return [
            'base' => round($base, 2),
            'discount' => round($discount, 2),
            'total' => round($base - $discount, 2),
            'tariff' => $tariff,
        ];
    }

    public function generateInvoice(Student $student, int $year, int $month): ?Invoice
    {
        $amounts = $this->monthlyAmount($student);

        if (! $amounts['tariff']) {
            return null; // tarif biriktirilmagan o'quvchiga hisob chiqarilmaydi
        }

        $existing = Invoice::where('student_id', $student->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($student, $year, $month, $amounts) {
            $invoice = Invoice::create([
                'school_id' => config('school.id'),
                'student_id' => $student->id,
                'tariff_id' => $amounts['tariff']->id,
                'number' => sprintf('INV-%04d%02d-%05d', $year, $month, $student->id),
                'period_year' => $year,
                'period_month' => $month,
                'base_amount' => $amounts['base'],
                'discount_amount' => $amounts['discount'],
                'total_amount' => $amounts['total'],
                'paid_amount' => 0,
                'status' => Invoice::STATUS_UNPAID,
                'due_date' => Carbon::create($year, $month, 10)->toDateString(),
                'generated_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $this->log($student, FinanceTransaction::TYPE_INVOICE, -$amounts['total'], $invoice,
                sprintf('%04d-%02d oyi uchun hisob-faktura', $year, $month));

            // Mavjud musbat balansdan avtomatik yopish
            $this->applyCreditToInvoice($student, $invoice);

            return $invoice->fresh();
        });
    }

    /**
     * Har oy boshida barcha faol o'quvchilar uchun hisob-faktura generatsiya qiladi.
     */
    public function generateMonthlyInvoices(int $year, int $month): int
    {
        $count = 0;

        Student::active()->with('activeTariff.tariff')->chunkById(200, function ($students) use ($year, $month, &$count) {
            foreach ($students as $student) {
                if ($this->generateInvoice($student, $year, $month)) {
                    $count++;
                }
            }
        });

        return $count;
    }

    /* ------------------------------------------------------------------ */
    /* To'lovlar */
    /* ------------------------------------------------------------------ */

    /**
     * To'lovni qabul qiladi va eng eski qarzlardan boshlab yopadi.
     * Ortiqcha summa musbat balans sifatida keyingi oyga o'tadi.
     */
    public function registerPayment(Student $student, array $data): Payment
    {
        return DB::transaction(function () use ($student, $data) {
            $payment = Payment::create([
                'school_id' => config('school.id'),
                'student_id' => $student->id,
                'invoice_id' => $data['invoice_id'] ?? null,
                'amount' => $data['amount'],
                'method' => $data['method'] ?? 'cash',
                'paid_at' => $data['paid_at'] ?? now(),
                'receipt_path' => $data['receipt_path'] ?? null,
                'note' => $data['note'] ?? null,
                'accepted_by' => $data['accepted_by'] ?? auth()->id(),
            ]);

            $this->log($student, FinanceTransaction::TYPE_PAYMENT, (float) $payment->amount, $payment,
                'To\'lov qabul qilindi ('.$this->methodLabel($payment->method).')');

            $this->allocatePayment($student, $payment);
            $balance = $this->recalculate($student);

            // Telegram bot uchun hodisa (stub)
            $this->notifications->paymentReceived($student, $payment, $balance);

            return $payment->fresh();
        });
    }

    /** To'lovni ochiq hisob-fakturalarga taqsimlaydi (eng eskisidan boshlab). */
    private function allocatePayment(Student $student, Payment $payment): void
    {
        $remaining = (float) $payment->amount;

        $invoices = Invoice::where('student_id', $student->id)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
            ->when($payment->invoice_id, fn ($q) => $q->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$payment->invoice_id]))
            ->orderBy('period_year')
            ->orderBy('period_month')
            ->get();

        foreach ($invoices as $invoice) {
            if ($remaining <= 0) {
                break;
            }

            $due = (float) $invoice->total_amount - (float) $invoice->paid_amount;

            if ($due <= 0) {
                continue;
            }

            $applied = min($due, $remaining);
            $remaining -= $applied;

            $invoice->paid_amount = (float) $invoice->paid_amount + $applied;
            $invoice->status = $invoice->paid_amount >= (float) $invoice->total_amount
                ? Invoice::STATUS_PAID
                : Invoice::STATUS_PARTIAL;
            $invoice->save();
        }
    }

    /** Yangi hisob-fakturani mavjud musbat balans hisobidan yopadi. */
    private function applyCreditToInvoice(Student $student, Invoice $invoice): void
    {
        $balance = (float) ($student->balance()->value('balance') ?? 0);

        if ($balance <= 0) {
            $this->recalculate($student);

            return;
        }

        $applied = min($balance, (float) $invoice->total_amount);

        $invoice->paid_amount = (float) $invoice->paid_amount + $applied;
        $invoice->status = $invoice->paid_amount >= (float) $invoice->total_amount
            ? Invoice::STATUS_PAID
            : Invoice::STATUS_PARTIAL;
        $invoice->save();

        $this->recalculate($student);
    }

    /* ------------------------------------------------------------------ */
    /* Balans */
    /* ------------------------------------------------------------------ */

    public function recalculate(Student $student): StudentBalance
    {
        $charged = (float) Invoice::where('student_id', $student->id)
            ->where('status', '!=', Invoice::STATUS_CANCELED)
            ->sum('total_amount');

        $paid = (float) Payment::where('student_id', $student->id)->sum('amount');

        return StudentBalance::updateOrCreate(
            ['student_id' => $student->id],
            [
                'school_id' => config('school.id'),
                'total_charged' => round($charged, 2),
                'total_paid' => round($paid, 2),
                // Musbat = oldindan to'lov, manfiy = qarzdorlik
                'balance' => round($paid - $charged, 2),
                'recalculated_at' => now(),
            ]
        );
    }

    /* ------------------------------------------------------------------ */
    /* Tarix */
    /* ------------------------------------------------------------------ */

    public function log(Student $student, string $type, float $amount, $reference = null, ?string $description = null, array $meta = []): FinanceTransaction
    {
        $balanceAfter = (float) ($student->balance()->value('balance') ?? 0) + $amount;

        return FinanceTransaction::create([
            'school_id' => config('school.id'),
            'student_id' => $student->id,
            'type' => $type,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'amount' => round($amount, 2),
            'balance_after' => round($balanceAfter, 2),
            'description' => $description,
            'meta' => $meta ?: null,
            'created_by' => auth()->id(),
        ]);
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'naqd',
            'card' => 'plastik karta',
            'transfer' => 'pul o\'tkazmasi',
            default => 'boshqa',
        };
    }
}
