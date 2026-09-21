<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Ish haqi hisoblash. Rejim har bir o'qituvchi profilida belgilanadi:
 *  - `fixed`      → oylik maosh
 *  - `per_lesson` → bir dars narxi × o'tkazilgan darslar soni
 */
class SalaryService
{
    public function conductedLessons(Teacher $teacher, int $year, int $month): int
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return Lesson::where('teacher_id', $teacher->id)
            ->conducted()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->count();
    }

    /**
     * @return array{salary_type:string, base_amount:float, lessons_count:int, per_lesson_rate:float, total:float}
     */
    public function calculate(Teacher $teacher, int $year, int $month, float $bonus = 0, float $deduction = 0): array
    {
        $lessons = $this->conductedLessons($teacher, $year, $month);

        if ($teacher->salary_type === Teacher::SALARY_PER_LESSON) {
            $base = $lessons * (float) $teacher->per_lesson_rate;
        } else {
            $base = (float) $teacher->fixed_salary;
        }

        return [
            'salary_type' => $teacher->salary_type,
            'base_amount' => round($base, 2),
            'lessons_count' => $lessons,
            'per_lesson_rate' => (float) $teacher->per_lesson_rate,
            'total' => round($base + $bonus - $deduction, 2),
        ];
    }

    public function generate(Teacher $teacher, int $year, int $month, float $bonus = 0, float $deduction = 0): TeacherSalary
    {
        $calc = $this->calculate($teacher, $year, $month, $bonus, $deduction);

        return TeacherSalary::updateOrCreate(
            ['teacher_id' => $teacher->id, 'period_year' => $year, 'period_month' => $month],
            [
                'school_id' => config('school.id'),
                'salary_type' => $calc['salary_type'],
                'base_amount' => $calc['base_amount'],
                'lessons_count' => $calc['lessons_count'],
                'per_lesson_rate' => $calc['per_lesson_rate'],
                'bonus' => $bonus,
                'deduction' => $deduction,
                'total_amount' => $calc['total'],
                'status' => TeacherSalary::STATUS_PENDING,
                'created_by' => auth()->id(),
            ]
        );
    }

    /** Barcha faol o'qituvchilar uchun oylik hisoblash */
    public function generateMonthly(int $year, int $month): int
    {
        $count = 0;

        foreach (Teacher::where('is_active', true)->get() as $teacher) {
            $this->generate($teacher, $year, $month);
            $count++;
        }

        return $count;
    }

    public function paginate(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        return TeacherSalary::query()
            ->with('teacher.user')
            ->filter($filters, [
                'teacher_id' => 'teacher_id',
                'year' => 'period_year',
                'month' => 'period_month',
                'status' => 'status',
            ])
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** Mukofot/ushlab qolishni tahrirlaydi va jamini qayta hisoblaydi. */
    public function update(TeacherSalary $salary, array $data): TeacherSalary
    {
        $bonus = (float) ($data['bonus'] ?? $salary->bonus);
        $deduction = (float) ($data['deduction'] ?? $salary->deduction);

        $salary->update([
            ...$data,
            'bonus' => $bonus,
            'deduction' => $deduction,
            'total_amount' => (float) $salary->base_amount + $bonus - $deduction,
        ]);

        return $salary->fresh('teacher.user');
    }

    public function markPaid(TeacherSalary $salary, ?string $note = null): TeacherSalary
    {
        $salary->update([
            'status' => TeacherSalary::STATUS_PAID,
            'paid_at' => now(),
            'note' => $note ?? $salary->note,
        ]);

        return $salary->fresh();
    }
}
