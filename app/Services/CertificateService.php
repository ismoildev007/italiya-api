<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Student;
use App\Support\Level;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sertifikat generatori: har bir o'quvchi OYIGA 1 MARTA, oyning 25-sanasidan keyin
 * (config: academy.certificate) o'z darajasini ko'rsatuvchi sertifikat oladi.
 * Ball = davomat×0.4 + uy ishi×0.3 + test×0.3 + ko'nikmalar kesimi.
 */
class CertificateService
{
    public function __construct(
        private readonly StudentStatsService $stats,
        private readonly NotificationService $notifications,
    ) {}

    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return Certificate::query()
            ->with('student.schoolClass')
            ->filter($filters, [
                'student_id' => 'student_id',
                'level' => 'level',
                'period_year' => 'period_year',
                'period_month' => 'period_month',
                'class_id' => ['student.class_id', 'has'],
            ])
            ->orderByDesc('issued_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Shu oy uchun olish mumkinmi: sana ≥ 25, hali olinmagan, kamida N ta test.
     *
     * @return array{eligible: bool, reason: ?string, available_from: string, already_issued: ?Certificate, preview: array}
     */
    public function eligibility(Student $student, ?Carbon $now = null): array
    {
        $now = $now ?? now();
        $day = (int) config('academy.certificate.available_from_day', 25);
        $availableFrom = $now->copy()->day(min($day, $now->daysInMonth))->startOfDay();

        $existing = Certificate::where('student_id', $student->id)
            ->where('period_year', $now->year)
            ->where('period_month', $now->month)
            ->first();

        $stats = $this->stats->forMonth($student, $now->year, $now->month);
        $minAttempts = (int) config('academy.certificate.min_attempts', 1);

        $reason = match (true) {
            $existing !== null => 'Bu oy uchun sertifikat allaqachon olingan.',
            $now->lessThan($availableFrom) => "Sertifikat oyning {$day}-sanasidan keyin olinadi.",
            ! $student->effectiveLevel() => 'O\'quvchining darajasi belgilanmagan.',
            $stats['tests']['count'] < $minAttempts => "Kamida {$minAttempts} ta test topshirilgan bo'lishi kerak.",
            default => null,
        };

        return [
            'eligible' => $reason === null,
            'reason' => $reason,
            'available_from' => $availableFrom->toDateString(),
            'period' => sprintf('%04d-%02d', $now->year, $now->month),
            'already_issued' => $existing,
            'preview' => $stats['scores'] + ['skills' => $stats['skills'], 'level' => $stats['level']],
        ];
    }

    /** Sertifikat yaratadi (qoidalar buzilsa 422). */
    public function issue(Student $student, ?Carbon $now = null, ?int $issuedBy = null, bool $force = false): Certificate
    {
        $now = $now ?? now();
        $check = $this->eligibility($student, $now);

        abort_if(! $force && ! $check['eligible'], 422, $check['reason']);
        abort_if($check['already_issued'] !== null, 422, 'Bu oy uchun sertifikat allaqachon olingan.');

        return DB::transaction(function () use ($student, $now, $issuedBy) {
            $stats = $this->stats->forMonth($student, $now->year, $now->month);
            $level = $student->effectiveLevel() ?? 'A1';

            $certificate = Certificate::create([
                'school_id' => config('school.id'),
                'student_id' => $student->id,
                'serial' => $this->nextSerial($now),
                'level' => $level,
                'track' => $student->effectiveTrack(),
                'period_year' => $now->year,
                'period_month' => $now->month,
                'title' => 'Certificato di Competenza Linguistica — '.Level::title($level),
                'attendance_score' => $stats['scores']['attendance'],
                'homework_score' => $stats['scores']['homework'],
                'test_score' => $stats['scores']['test'],
                'overall_score' => $stats['scores']['overall'],
                'skills' => $stats['skills'],
                'grade_label' => Level::gradeLabel($stats['scores']['overall']),
                'verify_code' => strtoupper(Str::random(10)),
                'issued_at' => $now,
                'issued_by' => $issuedBy ?? auth()->id(),
            ]);

            $this->notifications->certificateIssued($certificate->load('student'));

            return $certificate;
        });
    }

    public function verify(string $code): ?Certificate
    {
        return Certificate::with('student')->where('verify_code', strtoupper($code))->orWhere('serial', strtoupper($code))->first();
    }

    /** Chop etish uchun HTML (mockupdagi printableCertificate) */
    public function render(Certificate $certificate): string
    {
        $certificate->loadMissing('student.schoolClass');

        return view('certificates.show', [
            'certificate' => $certificate,
            'student' => $certificate->student,
            'school' => config('school'),
            'academy' => config('academy'),
            'levelTitle' => Level::title($certificate->level),
            'trackTitle' => config("academy.tracks.{$certificate->track}", $certificate->track),
            'skillLabels' => config('academy.skills'),
            'verifyUrl' => url("/api/v1/certificates/verify/{$certificate->verify_code}"),
        ])->render();
    }

    private function nextSerial(Carbon $now): string
    {
        $prefix = sprintf('ITA-%04d%02d-', $now->year, $now->month);
        $last = Certificate::where('serial', 'like', "{$prefix}%")->orderByDesc('serial')->value('serial');
        $next = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return sprintf('%s%04d', $prefix, $next);
    }
}
