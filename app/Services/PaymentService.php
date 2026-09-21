<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

class PaymentService
{
    public function __construct(
        private readonly FinanceService $finance,
    ) {}

    public function paginate(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        return Payment::query()
            ->with(['student.schoolClass', 'acceptedBy'])
            ->filter($filters, [
                'student_id' => 'student_id',
                'class_id' => ['student.class_id', 'has'],
                'method' => 'method',
                'from' => ['paid_at', 'date_from'],
                'to' => ['paid_at', 'date_to'],
            ])
            ->orderByDesc('paid_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * To'lovni qabul qiladi. Chek/screenshot fayl sifatida saqlanadi.
     * Ortiqcha summa musbat balans bo'lib keyingi oyga o'tadi.
     */
    public function register(array $data, ?UploadedFile $receipt = null): Payment
    {
        $student = Student::findOrFail($data['student_id']);

        if ($receipt) {
            $data['receipt_path'] = $receipt->store('receipts/'.now()->format('Y-m'), 'public');
        }

        return $this->finance
            ->registerPayment($student, $data)
            ->load(['student', 'acceptedBy']);
    }

    public function withDetails(Payment $payment): Payment
    {
        return $payment->load(['student.schoolClass', 'invoice', 'acceptedBy']);
    }

    /** To'lovni bekor qiladi va balansni qayta hisoblaydi. */
    public function cancel(Payment $payment): void
    {
        $student = $payment->student;
        $amount = (float) $payment->amount;

        $payment->delete();

        $this->finance->log($student, 'refund', -$amount, $payment, 'To\'lov bekor qilindi');
        $this->finance->recalculate($student);
    }
}
