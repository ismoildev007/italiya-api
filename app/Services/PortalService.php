<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\TestAssignment;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * O'quvchi va ota-ona kabineti — FAQAT O'QISH huquqi.
 *
 * O'quvchi faqat o'ziga tegishli ma'lumotlarni ko'radi,
 * ota-ona esa faqat o'z farzand(lar)iga tegishlisini.
 * Har bir metod avval kirish huquqini tekshiradi.
 */
class PortalService
{
    public function __construct(
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * Foydalanuvchi ko'ra oladigan o'quvchilar.
     *
     * @return Collection<int, Student>
     */
    public function accessibleStudents(User $user): Collection
    {
        if ($user->hasRole('student')) {
            return Student::where('user_id', $user->id)->with('schoolClass')->get();
        }

        if ($user->hasRole('parent')) {
            $guardian = $user->guardian;

            return $guardian
                ? $guardian->students()->with('schoolClass')->get()
                : collect();
        }

        // Admin/superadmin uchun cheklov yo'q (masalan qo'llab-quvvatlash maqsadida)
        return $user->isSuperadmin() || $user->hasRole('admin')
            ? Student::with('schoolClass')->get()
            : collect();
    }

    /**
     * Kabinet bosh ekrani uchun ro'yxat.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function children(User $user): Collection
    {
        return $this->accessibleStudents($user)->map(fn (Student $student) => [
            'id' => $student->id,
            'student_no' => $student->student_no,
            'full_name' => $student->full_name,
            'class' => $student->schoolClass?->name,
            'photo' => $student->photo,
        ])->values();
    }

    /** Kirish huquqini tekshiradi, aks holda 403. */
    public function authorizeStudent(User $user, Student $student): Student
    {
        $allowed = $this->accessibleStudents($user)->contains('id', $student->id);

        abort_unless($allowed, 403, 'Bu o\'quvchi ma\'lumotlariga kirish huquqingiz yo\'q.');

        return $student;
    }

    public function profile(Student $student): array
    {
        $student->load(['schoolClass.classTeacher.user', 'activeTariff.tariff', 'activeDiscount', 'balance', 'guardians']);

        return [
            'student' => [
                'id' => $student->id,
                'student_no' => $student->student_no,
                'full_name' => $student->full_name,
                'birth_date' => $student->birth_date?->toDateString(),
                'gender' => $student->gender,
                'phone' => $student->phone,
                'address' => $student->address,
                'photo' => $student->photo,
                'status' => $student->status,
            ],
            'class' => $student->schoolClass ? [
                'id' => $student->schoolClass->id,
                'name' => $student->schoolClass->name,
                'shift' => $student->schoolClass->shift_label,
                'class_teacher' => $student->schoolClass->classTeacher?->user?->full_name,
            ] : null,
            'tariff' => $student->activeTariff ? [
                'name' => $student->activeTariff->tariff?->name,
                'monthly_price' => (float) ($student->activeTariff->tariff?->monthly_price ?? 0),
            ] : null,
            'discount' => $student->activeDiscount ? [
                'type' => $student->activeDiscount->type,
                'value' => (float) $student->activeDiscount->value,
                'reason' => $student->activeDiscount->reason,
            ] : null,
            'balance' => $student->balance ? [
                'balance' => (float) $student->balance->balance,
                'is_debtor' => $student->balance->isDebtor(),
            ] : null,
        ];
    }

    /** O'z davomati — kunlik/haftalik/oylik tarix */
    public function attendance(Student $student, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $result = $this->attendance->studentHistory(
            $student,
            $from ?? now()->startOfMonth(),
            $to ?? now(),
        );

        return [
            'summary' => $result['summary'],
            'records' => $result['records']->map(fn (Attendance $item) => [
                'date' => $item->date->toDateString(),
                'status' => $item->status,
                'source' => $item->source,
                'note' => $item->note,
            ])->values(),
        ];
    }

    /** O'z baholari */
    public function grades(Student $student, array $filters = []): array
    {
        $grades = Grade::where('student_id', $student->id)
            ->with('subject')
            ->when($filters['subject_id'] ?? null, fn ($q, $id) => $q->where('subject_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('date')
            ->get();

        return [
            'summary' => [
                'total' => $grades->count(),
                'average' => $grades->count() ? round($grades->avg('value'), 2) : 0,
            ],
            'by_subject' => $grades->groupBy('subject_id')->map(fn ($items) => [
                'subject' => $items->first()->subject?->name,
                'average' => round($items->avg('value'), 2),
                'count' => $items->count(),
            ])->values(),
            'records' => $grades->map(fn (Grade $grade) => [
                'id' => $grade->id,
                'subject' => $grade->subject?->name,
                'value' => (float) $grade->value,
                'max_value' => (float) $grade->max_value,
                'type' => $grade->type,
                'date' => $grade->date->toDateString(),
                'comment' => $grade->comment,
            ])->values(),
        ];
    }

    /** Kutilayotgan testlar va tugatilgan natijalar. */
    public function tests(Student $student): array
    {
        return [
            'pending' => $this->pendingTests($student),
            'results' => $this->testResults($student),
        ];
    }

    /** O'z test natijalari — topshirgan javoblari bilan birga */
    public function testResults(Student $student): array
    {
        $attempts = TestAttempt::where('student_id', $student->id)
            ->with(['assignment.template.subject'])
            ->orderByDesc('submitted_at')
            ->get();

        return $attempts->map(fn (TestAttempt $attempt) => [
            'id' => $attempt->id,
            'test' => $attempt->assignment?->template?->title,
            'subject' => $attempt->assignment?->template?->subject?->name,
            'status' => $attempt->status,
            'total_score' => (float) $attempt->total_score,
            'max_score' => (float) $attempt->max_score,
            'percent' => (float) $attempt->percent,
            'submitted_at' => $attempt->submitted_at?->toDateTimeString(),
        ])->values()->all();
    }

    /** Bitta urinishning to'liq javoblari (matn + ovoz transkripti + audio) */
    public function attemptDetails(Student $student, TestAttempt $attempt): array
    {
        abort_unless($attempt->student_id === $student->id, 403, 'Bu natija sizga tegishli emas.');

        $attempt->load(['answers.question.options', 'assignment.template.subject']);

        return [
            'test' => $attempt->assignment?->template?->title,
            'subject' => $attempt->assignment?->template?->subject?->name,
            'total_score' => (float) $attempt->total_score,
            'max_score' => (float) $attempt->max_score,
            'percent' => (float) $attempt->percent,
            'answers' => $attempt->answers->map(fn ($answer) => [
                'question' => $answer->question?->body,
                'answer_mode' => $answer->question?->answer_mode,
                'answer_text' => $answer->answer_text,
                'transcript' => $answer->transcript,
                'audio_url' => $answer->audio_path ? asset('storage/'.$answer->audio_path) : null,
                'is_correct' => $answer->is_correct,
                'score' => (float) $answer->score,
                'feedback' => $answer->feedback,
            ])->values(),
        ];
    }

    /** To'lov holati (ota-ona uchun asosiy ekran) */
    public function payments(Student $student): array
    {
        $invoices = Invoice::where('student_id', $student->id)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->limit(12)
            ->get();

        $balance = $student->balance;

        return [
            'balance' => $balance ? [
                'balance' => (float) $balance->balance,
                'total_charged' => (float) $balance->total_charged,
                'total_paid' => (float) $balance->total_paid,
                'is_debtor' => $balance->isDebtor(),
            ] : null,
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'period' => $invoice->period_label,
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'remaining' => $invoice->remaining,
                'status' => $invoice->status,
                'due_date' => $invoice->due_date?->toDateString(),
            ])->values(),
            'payments' => $student->payments()->orderByDesc('paid_at')->limit(20)->get()
                ->map(fn ($payment) => [
                    'amount' => (float) $payment->amount,
                    'method' => $payment->method,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'receipt_url' => $payment->receipt_path ? asset('storage/'.$payment->receipt_path) : null,
                ])->values(),
        ];
    }

    /** Topshirilishi kerak bo'lgan testlar */
    public function pendingTests(Student $student): array
    {
        $assignments = TestAssignment::where('status', 'active')
            ->where(function ($q) use ($student) {
                $q->where('student_id', $student->id)
                    ->orWhere('class_id', $student->class_id);
            })
            ->with(['template.subject'])
            ->get();

        $done = TestAttempt::where('student_id', $student->id)
            ->whereIn('status', [TestAttempt::STATUS_SUBMITTED, TestAttempt::STATUS_GRADED])
            ->pluck('test_assignment_id');

        return $assignments->reject(fn ($a) => $done->contains($a->id))
            ->map(fn (TestAssignment $assignment) => [
                'assignment_id' => $assignment->id,
                'title' => $assignment->template?->title,
                'subject' => $assignment->template?->subject?->name,
                'question_count' => $assignment->template?->question_count,
                'duration_minutes' => $assignment->template?->duration_minutes,
                'due_at' => $assignment->due_at?->toDateTimeString(),
            ])->values()->all();
    }
}
