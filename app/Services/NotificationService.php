<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Certificate;
use App\Models\Grade;
use App\Models\Homework;
use App\Models\HomeworkSubmission;
use App\Models\Meeting;
use App\Models\MonthlyReport;
use App\Models\NotificationEvent;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentBalance;
use App\Models\TestAssignment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Telegram bot integratsiyasi uchun HODISA GENERATORI (stub).
 *
 * Hozircha bot logikasi ulanmagan — bu servis faqat `notification_events`
 * jadvaliga yozuv qo'yadi. Keyinchalik queue job / bot shu yozuvlarni o'qib
 * xabar yuboradi va statusni `sent` ga o'zgartiradi. Boshqa modullar
 * (davomat, baho, to'lov) faqat shu servisga murojaat qiladi — ularning
 * kodini o'zgartirmasdan real botni ulash mumkin.
 */
class NotificationService
{
    /** Kunlik davomat natijasi */
    public function attendanceMarked(Attendance $attendance): void
    {
        $student = $attendance->student ?? Student::find($attendance->student_id);

        if (! $student) {
            return;
        }

        $label = match ($attendance->status) {
            Attendance::STATUS_PRESENT => 'darsga keldi',
            Attendance::STATUS_ABSENT => 'darsga kelmadi',
            Attendance::STATUS_LATE => 'darsga kech qoldi',
            Attendance::STATUS_EXCUSED => 'sababli sabab bilan qatnashmadi',
            default => 'davomati belgilandi',
        };

        $this->dispatchToGuardians(
            $student,
            NotificationEvent::TYPE_ATTENDANCE,
            'Davomat',
            "{$student->full_name} {$attendance->date->format('d.m.Y')} kuni {$label}.",
            $attendance,
            ['status' => $attendance->status, 'date' => $attendance->date->toDateString()],
        );
    }

    /** Yangi baho qo'yilganda */
    public function gradeCreated(Grade $grade): void
    {
        $student = $grade->student ?? Student::find($grade->student_id);

        if (! $student) {
            return;
        }

        $subject = $grade->subject?->name ?? 'fan';

        $this->dispatchToGuardians(
            $student,
            NotificationEvent::TYPE_GRADE,
            'Yangi baho',
            "{$student->full_name} — {$subject}: {$grade->value} ball ({$grade->date->format('d.m.Y')}).",
            $grade,
            ['value' => (float) $grade->value, 'subject' => $subject, 'type' => $grade->type],
        );
    }

    /** To'lov qabul qilinganda (chek/screenshot bilan) */
    public function paymentReceived(Student $student, Payment $payment, ?StudentBalance $balance = null): void
    {
        $amount = number_format((float) $payment->amount, 0, '.', ' ');

        $this->dispatchToGuardians(
            $student,
            NotificationEvent::TYPE_PAYMENT,
            'To\'lov qabul qilindi',
            "{$student->full_name} uchun {$amount} so'm to'lov qabul qilindi.",
            $payment,
            [
                'amount' => (float) $payment->amount,
                'method' => $payment->method,
                'receipt' => $payment->receipt_path,
                'balance' => $balance ? (float) $balance->balance : null,
            ],
        );
    }

    /** Qarzdorlik yuzaga kelganda / eslatma */
    public function debtReminder(Student $student, float $debt): void
    {
        $amount = number_format(abs($debt), 0, '.', ' ');

        $this->dispatchToGuardians(
            $student,
            NotificationEvent::TYPE_DEBT,
            'Qarzdorlik eslatmasi',
            "{$student->full_name} bo'yicha {$amount} so'm qarzdorlik mavjud.",
            null,
            ['debt' => round($debt, 2)],
        );
    }

    /** Test biriktirilganda — o'quvchi(lar)ga */
    public function testAssigned(TestAssignment $assignment): void
    {
        $template = $assignment->template;
        $kind = match ($template?->kind) {
            'mock' => 'Mock imtihon',
            'homework' => 'Uy ishi testi',
            'test' => 'Nazorat testi',
            'placement' => 'Daraja aniqlash testi',
            default => 'Mashq',
        };
        $due = $assignment->due_at ? ' Muddat: '.$assignment->due_at->format('d.m.Y H:i').'.' : '';

        foreach ($this->targetStudents($assignment->student_id, $assignment->class_id) as $student) {
            $this->dispatchToStudent(
                $student,
                NotificationEvent::TYPE_TEST_ASSIGNED,
                $kind,
                "{$kind}: «{$template?->title}» ({$template?->level}).{$due} Testni platformada yoki botda /tests buyrug'i bilan yeching.",
                $assignment,
                ['assignment_id' => $assignment->id, 'kind' => $template?->kind],
            );
        }
    }

    /** Uy ishi berilganda — o'quvchi va ota-onaga */
    public function homeworkAssigned(Homework $homework): void
    {
        $due = $homework->due_at ? ' Muddat: '.$homework->due_at->format('d.m.Y H:i').'.' : '';

        foreach ($this->targetStudents($homework->student_id, $homework->class_id) as $student) {
            $message = "{$student->firstname} uchun yangi uy ishi: «{$homework->title}».{$due}";

            $this->dispatchToStudent($student, NotificationEvent::TYPE_HOMEWORK_ASSIGNED, 'Uy ishi', $message, $homework, ['homework_id' => $homework->id]);
            $this->dispatchToGuardians($student, NotificationEvent::TYPE_HOMEWORK_ASSIGNED, 'Uy ishi', $message, $homework, ['homework_id' => $homework->id]);
        }
    }

    /** Uy ishi tekshirilganda */
    public function homeworkGraded(HomeworkSubmission $submission): void
    {
        $student = $submission->student ?? Student::find($submission->student_id);
        $homework = $submission->homework;

        if (! $student || ! $homework) {
            return;
        }

        $message = "«{$homework->title}» uy ishi tekshirildi: {$submission->score}/{$homework->max_score} ball.".($submission->feedback ? " Izoh: {$submission->feedback}" : '');

        $this->dispatchToStudent($student, NotificationEvent::TYPE_HOMEWORK_GRADED, 'Uy ishi baholandi', $message, $submission);
        $this->dispatchToGuardians($student, NotificationEvent::TYPE_HOMEWORK_GRADED, 'Uy ishi baholandi', "{$student->full_name}: ".$message, $submission);
    }

    /** Oy oxiridagi o'zlashtirish tahlili — ota-onaga */
    public function monthlyReport(MonthlyReport $report, string $text): void
    {
        $student = $report->student ?? Student::find($report->student_id);

        if (! $student) {
            return;
        }

        $this->dispatchToGuardians($student, NotificationEvent::TYPE_MONTHLY_REPORT, 'Oylik hisobot', $text, $report, ['year' => $report->year, 'month' => $report->month]);
        $this->dispatchToStudent($student, NotificationEvent::TYPE_MONTHLY_REPORT, 'Oylik hisobot', $text, $report);
    }

    /** Sertifikat berilganda */
    public function certificateIssued(Certificate $certificate): void
    {
        $student = $certificate->student ?? Student::find($certificate->student_id);

        if (! $student) {
            return;
        }

        $message = "{$student->full_name} {$certificate->period_label} oyi uchun {$certificate->level} darajasi sertifikatini oldi: {$certificate->overall_score}/100 ({$certificate->grade_label}). Tekshirish kodi: {$certificate->verify_code}";

        $this->dispatchToGuardians($student, NotificationEvent::TYPE_CERTIFICATE, 'Sertifikat', $message, $certificate, ['serial' => $certificate->serial]);
        $this->dispatchToStudent($student, NotificationEvent::TYPE_CERTIFICATE, 'Sertifikat', $message, $certificate, ['serial' => $certificate->serial]);
    }

    /** Onlayn dars (Zoom) rejalashtirilganda — tegishli o'quvchilarga */
    public function meetingScheduled(Meeting $meeting, iterable $students): void
    {
        $message = "Onlayn dars: «{$meeting->title}» — {$meeting->starts_at->format('d.m.Y H:i')}. Havola: {$meeting->link}";

        foreach ($students as $student) {
            $this->dispatchToStudent($student, NotificationEvent::TYPE_MEETING, 'Zoom dars', $message, $meeting, ['meeting_id' => $meeting->id]);
        }
    }

    /** Daraja o'zgarganda */
    public function levelChanged(Student $student, ?string $from, string $to): void
    {
        $message = "{$student->full_name} darajasi {$from} → {$to} ga o'zgartirildi.";

        $this->dispatchToStudent($student, NotificationEvent::TYPE_LEVEL_CHANGED, 'Daraja', $message);
        $this->dispatchToGuardians($student, NotificationEvent::TYPE_LEVEL_CHANGED, 'Daraja', $message);
    }

    /** @return Collection<int, Student> */
    private function targetStudents(?int $studentId, ?int $classId)
    {
        if ($studentId) {
            return Student::whereKey($studentId)->get();
        }

        return $classId ? Student::active()->where('class_id', $classId)->get() : collect();
    }

    /** Brauzer push (Firebase) — sozlanmagan bo'lsa jim o'tadi */
    private function push(?int $userId, string $title, string $message, string $type, array $payload = []): void
    {
        try {
            app(FirebasePushService::class)->sendToUser($userId, $title, Str::limit($message, 180), ['type' => $type] + array_map('strval', array_filter($payload, 'is_scalar')));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Hodisani o'quvchining o'ziga (Telegram) navbatga qo'yadi.
     */
    private function dispatchToStudent(
        Student $student,
        string $type,
        string $title,
        string $message,
        $source = null,
        array $payload = [],
    ): void {
        $this->push($student->user_id, $title, $message, $type, $payload);

        NotificationEvent::create([
            'school_id' => config('school.id'),
            'type' => $type,
            'channel' => 'telegram',
            'recipient_type' => 'student',
            'recipient_id' => $student->id,
            'recipient_address' => $student->telegram_chat_id,
            'student_id' => $student->id,
            'source_type' => $source ? $source::class : null,
            'source_id' => $source?->getKey(),
            'title' => $title,
            'message' => $message,
            'payload' => $payload ?: null,
            'status' => NotificationEvent::STATUS_PENDING,
        ]);
    }

    /**
     * Hodisani o'quvchining barcha ota-onalariga navbatga qo'yadi.
     */
    private function dispatchToGuardians(
        Student $student,
        string $type,
        string $title,
        string $message,
        $source = null,
        array $payload = [],
    ): void {
        $guardians = $student->guardians()->get();

        if ($guardians->isEmpty()) {
            return;
        }

        foreach ($guardians as $guardian) {
            $this->push($guardian->user_id, $title, $message, $type, $payload);

            NotificationEvent::create([
                'school_id' => config('school.id'),
                'type' => $type,
                'channel' => 'telegram',
                'recipient_type' => 'guardian',
                'recipient_id' => $guardian->id,
                'recipient_address' => $guardian->telegram_chat_id,
                'student_id' => $student->id,
                'source_type' => $source ? $source::class : null,
                'source_id' => $source?->getKey(),
                'title' => $title,
                'message' => $message,
                'payload' => $payload ?: null,
                // Botga bog'lanmagan ota-onaga xabar yuborilmaydi, lekin hodisa saqlanadi
                'status' => NotificationEvent::STATUS_PENDING,
            ]);
        }
    }
}
