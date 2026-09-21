<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\SkillProgress;
use App\Models\Student;
use App\Models\StudentBalance;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kunlik / haftalik / oylik / yillik hisobotlar.
 * Filtrlar: sinf, fan, davr, umumiy maktab kesimi.
 */
class ReportService
{
    /** Bosh sahifa uchun umumiy ko'rsatkichlar */
    public function dashboard(): array
    {
        $today = now()->toDateString();

        $attendanceToday = Attendance::whereDate('date', $today)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $balances = StudentBalance::selectRaw('
            SUM(CASE WHEN balance < 0 THEN balance ELSE 0 END) as debt,
            SUM(CASE WHEN balance > 0 THEN balance ELSE 0 END) as credit,
            SUM(CASE WHEN balance < 0 THEN 1 ELSE 0 END) as debtors
        ')->first();

        return [
            'school' => [
                'name' => config('school.name'),
                'currency' => config('school.currency'),
            ],
            'students' => [
                'total' => Student::active()->count(),
                'classes' => SchoolClass::where('is_active', true)->count(),
            ],
            'teachers' => [
                'total' => Teacher::where('is_active', true)->count(),
                'fixed' => Teacher::where('is_active', true)->where('salary_type', Teacher::SALARY_FIXED)->count(),
                'per_lesson' => Teacher::where('is_active', true)->where('salary_type', Teacher::SALARY_PER_LESSON)->count(),
            ],
            'attendance_today' => [
                'present' => (int) ($attendanceToday['present'] ?? 0),
                'absent' => (int) ($attendanceToday['absent'] ?? 0),
                'late' => (int) ($attendanceToday['late'] ?? 0),
                'excused' => (int) ($attendanceToday['excused'] ?? 0),
            ],
            'finance' => [
                'debt_total' => round(abs((float) ($balances->debt ?? 0)), 2),
                'credit_total' => round((float) ($balances->credit ?? 0), 2),
                'debtors_count' => (int) ($balances->debtors ?? 0),
                'paid_this_month' => round((float) Payment::whereYear('paid_at', now()->year)
                    ->whereMonth('paid_at', now()->month)->sum('amount'), 2),
                'invoiced_this_month' => round((float) Invoice::where('period_year', now()->year)
                    ->where('period_month', now()->month)->sum('total_amount'), 2),
            ],
        ];
    }

    /** Davomat hisoboti — sinf/o'quvchi kesimida */
    public function attendance(array $filters): array
    {
        [$from, $to] = $this->period($filters);

        $query = Attendance::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('class_id', $id))
            ->when($filters['student_id'] ?? null, fn ($q, $id) => $q->where('student_id', $id));

        $totals = (clone $query)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $byClass = (clone $query)
            ->join('school_classes', 'school_classes.id', '=', 'attendances.class_id')
            ->select(
                'school_classes.id',
                'school_classes.name',
                DB::raw("SUM(CASE WHEN attendances.status = 'present' THEN 1 ELSE 0 END) as present"),
                DB::raw("SUM(CASE WHEN attendances.status = 'absent' THEN 1 ELSE 0 END) as absent"),
                DB::raw("SUM(CASE WHEN attendances.status = 'late' THEN 1 ELSE 0 END) as late"),
                DB::raw("SUM(CASE WHEN attendances.status = 'excused' THEN 1 ELSE 0 END) as excused"),
                DB::raw('count(*) as total')
            )
            ->groupBy('school_classes.id', 'school_classes.name')
            ->orderBy('school_classes.name')
            ->get()
            ->map(fn ($row) => [
                'class_id' => $row->id,
                'class' => $row->name,
                'present' => (int) $row->present,
                'absent' => (int) $row->absent,
                'late' => (int) $row->late,
                'excused' => (int) $row->excused,
                'total' => (int) $row->total,
                'rate' => $row->total > 0 ? round(($row->present + $row->late) / $row->total * 100, 2) : 0,
            ]);

        $total = (int) $totals->sum();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'present' => (int) ($totals['present'] ?? 0),
                'absent' => (int) ($totals['absent'] ?? 0),
                'late' => (int) ($totals['late'] ?? 0),
                'excused' => (int) ($totals['excused'] ?? 0),
                'total' => $total,
                'rate' => $total > 0
                    ? round(((int) ($totals['present'] ?? 0) + (int) ($totals['late'] ?? 0)) / $total * 100, 2)
                    : 0,
            ],
            'by_class' => $byClass,
        ];
    }

    /** Baholar va test natijalari hisoboti */
    public function grades(array $filters): array
    {
        [$from, $to] = $this->period($filters);

        $query = Grade::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->where('class_id', $id))
            ->when($filters['subject_id'] ?? null, fn ($q, $id) => $q->where('subject_id', $id))
            ->when($filters['student_id'] ?? null, fn ($q, $id) => $q->where('student_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type));

        $bySubject = (clone $query)
            ->join('subjects', 'subjects.id', '=', 'grades.subject_id')
            ->select('subjects.id', 'subjects.name', DB::raw('AVG(grades.value) as average'), DB::raw('count(*) as total'))
            ->groupBy('subjects.id', 'subjects.name')
            ->orderBy('subjects.name')
            ->get()
            ->map(fn ($row) => [
                'subject_id' => $row->id,
                'subject' => $row->name,
                'average' => round((float) $row->average, 2),
                'total' => (int) $row->total,
            ]);

        $distribution = (clone $query)
            ->select('value', DB::raw('count(*) as total'))
            ->groupBy('value')
            ->orderBy('value')
            ->pluck('total', 'value');

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total' => (clone $query)->count(),
                'average' => round((float) (clone $query)->avg('value'), 2),
            ],
            'distribution' => $distribution,
            'by_subject' => $bySubject,
        ];
    }

    /** To'lovlar, qarzdorlik, balans holati */
    public function payments(array $filters): array
    {
        [$from, $to] = $this->period($filters);

        $payments = Payment::query()
            ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->whereHas('student', fn ($q) => $q->where('class_id', $id)))
            ->when($filters['method'] ?? null, fn ($q, $method) => $q->where('method', $method));

        $byMethod = (clone $payments)
            ->select('method', DB::raw('SUM(amount) as total'), DB::raw('count(*) as count'))
            ->groupBy('method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'total' => round((float) $row->total, 2),
                'count' => (int) $row->count,
            ]);

        $invoices = Invoice::query()
            ->when($filters['year'] ?? null, fn ($q, $year) => $q->where('period_year', $year))
            ->when($filters['month'] ?? null, fn ($q, $month) => $q->where('period_month', $month));

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'paid_total' => round((float) (clone $payments)->sum('amount'), 2),
                'payments_count' => (clone $payments)->count(),
                'invoiced_total' => round((float) (clone $invoices)->sum('total_amount'), 2),
                'collected_total' => round((float) (clone $invoices)->sum('paid_amount'), 2),
            ],
            'by_method' => $byMethod,
            'invoice_status' => (clone $invoices)
                ->select('status', DB::raw('count(*) as total'), DB::raw('SUM(total_amount) as amount'))
                ->groupBy('status')
                ->get()
                ->map(fn ($row) => [
                    'status' => $row->status,
                    'count' => (int) $row->total,
                    'amount' => round((float) $row->amount, 2),
                ]),
        ];
    }

    /** Qarzdorlar ro'yxati */
    public function debtors(array $filters): array
    {
        $rows = StudentBalance::query()
            ->with(['student.schoolClass'])
            ->where('balance', '<', 0)
            ->when($filters['class_id'] ?? null, fn ($q, $id) => $q->whereHas('student', fn ($q) => $q->where('class_id', $id)))
            ->orderBy('balance')
            ->get()
            ->map(fn (StudentBalance $balance) => [
                'student_id' => $balance->student_id,
                'student' => $balance->student?->full_name,
                'student_no' => $balance->student?->student_no,
                'class' => $balance->student?->schoolClass?->name,
                'debt' => round(abs((float) $balance->balance), 2),
                'total_charged' => round((float) $balance->total_charged, 2),
                'total_paid' => round((float) $balance->total_paid, 2),
            ]);

        return [
            'count' => $rows->count(),
            'total_debt' => round((float) $rows->sum('debt'), 2),
            'students' => $rows->values(),
        ];
    }

    /** Ko'nikmalar bo'yicha o'zlashtirish — daraja/guruh/yo'nalish kesimida */
    public function skills(array $filters): array
    {
        $rows = SkillProgress::query()
            ->with('student:id,firstname,lastname,level,class_id')
            ->when($filters['level'] ?? null, fn ($q, $l) => $q->where('level', $l))
            ->when($filters['class_id'] ?? null, fn ($q, $c) => $q->whereHas('student', fn ($s) => $s->where('class_id', $c)))
            ->when($filters['track'] ?? null, fn ($q, $t) => $q->whereHas('student', fn ($s) => $s->where('track', $t)))
            ->get();

        $bySkill = $rows->groupBy('skill')->map(fn ($items, $skill) => [
            'skill' => $skill,
            'label' => config("academy.skills.{$skill}", $skill),
            'average' => round((float) $items->avg('percent'), 2),
            'students' => $items->count(),
        ])->values();

        $byLevel = $rows->groupBy('level')->map(fn ($items, $level) => [
            'level' => $level,
            'average' => round((float) $items->avg('percent'), 2),
            'students' => $items->pluck('student_id')->unique()->count(),
        ])->values();

        $weakest = $rows->groupBy('student_id')->map(fn ($items) => [
            'student_id' => $items->first()->student_id,
            'student' => $items->first()->student?->full_name,
            'level' => $items->first()->level,
            'average' => round((float) $items->avg('percent'), 2),
        ])->sortBy('average')->take(10)->values();

        return [
            'by_skill' => $bySkill,
            'by_level' => $byLevel,
            'students_needing_attention' => $weakest,
            'total_students' => $rows->pluck('student_id')->unique()->count(),
        ];
    }

    /** O'qituvchilar ish haqi hisoboti */
    public function salaries(array $filters): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $month = $filters['month'] ?? null;

        $query = TeacherSalary::with('teacher.user')
            ->where('period_year', $year)
            ->when($month, fn ($q, $m) => $q->where('period_month', $m));

        $rows = $query->get();

        return [
            'period' => ['year' => $year, 'month' => $month ? (int) $month : null],
            'summary' => [
                'total' => round((float) $rows->sum('total_amount'), 2),
                'paid' => round((float) $rows->where('status', TeacherSalary::STATUS_PAID)->sum('total_amount'), 2),
                'pending' => round((float) $rows->where('status', TeacherSalary::STATUS_PENDING)->sum('total_amount'), 2),
                'teachers' => $rows->pluck('teacher_id')->unique()->count(),
            ],
            'rows' => $rows->map(fn (TeacherSalary $salary) => [
                'teacher_id' => $salary->teacher_id,
                'teacher' => $salary->teacher?->user?->full_name,
                'period' => $salary->period_label,
                'salary_type' => $salary->salary_type,
                'lessons_count' => $salary->lessons_count,
                'base_amount' => (float) $salary->base_amount,
                'bonus' => (float) $salary->bonus,
                'deduction' => (float) $salary->deduction,
                'total_amount' => (float) $salary->total_amount,
                'status' => $salary->status,
            ])->values(),
        ];
    }

    /**
     * Davr filtri: `period` (day|week|month|year) yoki aniq from/to.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(array $filters): array
    {
        if (! empty($filters['from']) && ! empty($filters['to'])) {
            return [Carbon::parse($filters['from'])->startOfDay(), Carbon::parse($filters['to'])->endOfDay()];
        }

        return match ($filters['period'] ?? 'month') {
            'day' => [now()->startOfDay(), now()->endOfDay()],
            'week' => [now()->startOfWeek(), now()->endOfWeek()],
            'year' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }
}
