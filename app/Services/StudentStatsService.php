<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\GameSession;
use App\Models\HomeworkSubmission;
use App\Models\Student;
use App\Models\TestAttempt;
use App\Models\UnitProgress;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * O'quvchining davr bo'yicha statistikasi — sertifikat, oylik hisobot va dashboard uchun
 * yagona manba. Mockup formulasi: davomat 40% + uy ishi 30% + test 30%.
 */
class StudentStatsService
{
    public function __construct(private readonly SkillProgressService $skills) {}

    public function forPeriod(Student $student, CarbonInterface $from, CarbonInterface $to): array
    {
        $attendance = Attendance::where('student_id', $student->id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get();

        $attended = $attendance->whereIn('status', [Attendance::STATUS_PRESENT, Attendance::STATUS_LATE])->count();
        $attendancePercent = $attendance->count() ? round($attended / $attendance->count() * 100, 2) : null;

        $attempts = TestAttempt::where('student_id', $student->id)
            ->whereIn('status', [TestAttempt::STATUS_GRADED, TestAttempt::STATUS_SUBMITTED])
            ->whereBetween('submitted_at', [$from, $to])
            ->with('assignment.template:id,title,kind,level,skill')
            ->get();

        $testPercent = $attempts->count() ? round($attempts->avg('percent'), 2) : null;

        $homework = HomeworkSubmission::where('student_id', $student->id)
            ->where('status', HomeworkSubmission::STATUS_CHECKED)
            ->whereBetween('graded_at', [$from, $to])
            ->with('homework:id,max_score')
            ->get();

        $homeworkPercent = $homework->count()
            ? round($homework->avg(fn ($s) => $s->homework?->max_score ? $s->score / $s->homework->max_score * 100 : $s->score), 2)
            : null;

        $games = GameSession::where('student_id', $student->id)->whereBetween('finished_at', [$from, $to])->get();
        $units = UnitProgress::where('student_id', $student->id)->whereBetween('completed_at', [$from, $to])->count();

        $level = $student->effectiveLevel();
        $skills = $level ? $this->skills->forLevel($student, $level) : [];

        $overall = round(($attendancePercent ?? 0) * 0.4 + ($homeworkPercent ?? 0) * 0.3 + ($testPercent ?? 0) * 0.3, 2);

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'level' => $level,
            'attendance' => [
                'percent' => $attendancePercent,
                'total' => $attendance->count(),
                'present' => $attended,
                'absent' => $attendance->where('status', Attendance::STATUS_ABSENT)->count(),
            ],
            'tests' => [
                'count' => $attempts->count(),
                'average_percent' => $testPercent,
                'best_percent' => (float) ($attempts->max('percent') ?? 0),
                'mocks' => $attempts->filter(fn ($a) => $a->assignment?->template?->kind === 'mock')->count(),
                'items' => $attempts->map(fn ($a) => [
                    'title' => $a->assignment?->template?->title,
                    'kind' => $a->assignment?->template?->kind,
                    'percent' => (float) $a->percent,
                    'date' => $a->submitted_at?->toDateString(),
                ])->values()->all(),
            ],
            'homework' => [
                'count' => $homework->count(),
                'average_percent' => $homeworkPercent,
            ],
            'games' => [
                'sessions' => $games->count(),
                'score' => (int) $games->sum('score'),
                'accuracy' => $games->sum('total_rounds') ? round($games->sum('correct') / $games->sum('total_rounds') * 100, 2) : null,
            ],
            'units_completed' => $units,
            'skills' => collect($skills)->map(fn ($s) => $s['percent'])->all(),
            'scores' => [
                'attendance' => $attendancePercent ?? 0,
                'homework' => $homeworkPercent ?? 0,
                'test' => $testPercent ?? 0,
                'overall' => $overall,
            ],
        ];
    }

    public function forMonth(Student $student, int $year, int $month): array
    {
        $from = Carbon::create($year, $month, 1)->startOfDay();

        return $this->forPeriod($student, $from, $from->copy()->endOfMonth());
    }
}
