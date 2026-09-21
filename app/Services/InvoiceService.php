<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class InvoiceService
{
    public function __construct(
        private readonly FinanceService $finance,
    ) {}

    public function paginate(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['student.schoolClass', 'tariff'])
            ->filter($filters, [
                'student_id' => 'student_id',
                'class_id' => ['student.class_id', 'has'],
                'status' => 'status',
                'year' => 'period_year',
                'month' => 'period_month',
            ])
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderBy('student_id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function withDetails(Invoice $invoice): Invoice
    {
        return $invoice->load(['student.schoolClass', 'tariff', 'payments.acceptedBy']);
    }

    /** Bitta o'quvchi uchun hisob-faktura. */
    public function generateFor(array $data): Invoice
    {
        $student = Student::findOrFail($data['student_id']);

        $invoice = $this->finance->generateInvoice($student, $data['year'], $data['month']);

        abort_unless($invoice, 422, 'O\'quvchiga tarif biriktirilmagan.');

        return $invoice->load('student');
    }

    /** Barcha faol o'quvchilar uchun oylik generatsiya. */
    public function generateMonthly(int $year, int $month): int
    {
        return $this->finance->generateMonthlyInvoices($year, $month);
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);

        if (($data['status'] ?? null) === Invoice::STATUS_CANCELED) {
            $this->finance->recalculate($invoice->student);
        }

        return $invoice->fresh('student');
    }
}
