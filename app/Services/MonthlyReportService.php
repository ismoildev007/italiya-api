<?php

namespace App\Services;

use App\Models\MonthlyReport;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Oy oxirida har bir o'quvchi uchun o'zlashtirish tahlili tuziladi va
 * ota-onaga (Telegram) yuboriladi: testlar, ko'nikmalar, davomat, uy ishi, o'yinlar.
 */
class MonthlyReportService
{
    public function __construct(
        private readonly StudentStatsService $stats,
        private readonly AiEvaluationService $ai,
        private readonly NotificationService $notifications,
    ) {}

    public function build(Student $student, int $year, int $month, bool $withAi = true): MonthlyReport
    {
        $data = $this->stats->forMonth($student, $year, $month);
        $summary = $withAi ? $this->ai->monthlySummary($data, $student->full_name) : null;

        return MonthlyReport::updateOrCreate(
            ['student_id' => $student->id, 'year' => $year, 'month' => $month],
            ['school_id' => config('school.id'), 'data' => $data, 'summary' => $summary],
        );
    }

    /** Hisobotni navbatga qo'yadi (Telegram). */
    public function send(MonthlyReport $report): MonthlyReport
    {
        $this->notifications->monthlyReport($report->load('student'), $this->format($report));
        $report->update(['sent_at' => now()]);

        return $report->fresh();
    }

    /**
     * Barcha faol o'quvchilar uchun tuzadi va yuboradi. Yuborilganlar sonini qaytaradi.
     */
    public function dispatchAll(int $year, int $month, bool $withAi = true): int
    {
        $count = 0;

        Student::active()->chunkById(100, function (Collection $students) use ($year, $month, $withAi, &$count) {
            foreach ($students as $student) {
                $report = $this->build($student, $year, $month, $withAi);

                if (! $report->sent_at) {
                    $this->send($report);
                    $count++;
                }
            }
        });

        return $count;
    }

    /** Telegram uchun matn (HTML). */
    public function format(MonthlyReport $report): string
    {
        $d = $report->data;
        $student = $report->student;
        $skills = collect($d['skills'] ?? [])
            ->map(fn ($v, $k) => '• '.config("academy.skills.{$k}", $k).": {$v}%")
            ->implode("\n");

        $lines = [
            "📊 <b>Oylik hisobot — {$report->year}-{$report->month}</b>",
            "👤 {$student->full_name} · daraja: <b>{$d['level']}</b>",
            '',
            "✅ Davomat: {$this->pct($d['attendance']['percent'])} ({$d['attendance']['present']}/{$d['attendance']['total']} dars)",
            "📝 Testlar: {$d['tests']['count']} ta, o'rtacha {$this->pct($d['tests']['average_percent'])}, eng yaxshi {$this->pct($d['tests']['best_percent'])}",
            "📚 Uy ishi: {$d['homework']['count']} ta, o'rtacha {$this->pct($d['homework']['average_percent'])}",
            "🎮 O'yinlar: {$d['games']['sessions']} ta, {$d['games']['score']} ball",
            '',
            '<b>Ko\'nikmalar:</b>',
            $skills ?: '—',
            '',
            "⭐ Umumiy ball: <b>{$d['scores']['overall']}/100</b>",
        ];

        if ($report->summary) {
            $lines[] = '';
            $lines[] = '💬 '.$report->summary;
        }

        return implode("\n", $lines);
    }

    private function pct(mixed $value): string
    {
        return $value === null ? '—' : rtrim(rtrim(number_format((float) $value, 1), '0'), '.').'%';
    }
}
