<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\MonthlyReportRequest;
use App\Http\Requests\Report\ReportRequest;
use App\Http\Requests\Report\SkillsReportRequest;
use App\Http\Resources\MonthlyReportResource;
use App\Models\Student;
use App\Services\MonthlyReportService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

/**
 * Kunlik / haftalik / oylik / yillik hisobotlar.
 * Umumiy filtrlar: `period` (day|week|month|year) yoki `from`/`to`,
 * hamda `class_id`, `subject_id`, `student_id`.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
    ) {}

    public function dashboard(): JsonResponse
    {
        return $this->ok($this->reports->dashboard());
    }

    public function attendance(ReportRequest $request): JsonResponse
    {
        return $this->ok($this->reports->attendance($request->filters()));
    }

    public function grades(ReportRequest $request): JsonResponse
    {
        return $this->ok($this->reports->grades($request->filters()));
    }

    public function payments(ReportRequest $request): JsonResponse
    {
        return $this->ok($this->reports->payments($request->filters()));
    }

    public function debtors(ReportRequest $request): JsonResponse
    {
        return $this->ok($this->reports->debtors($request->filters()));
    }

    public function salaries(ReportRequest $request): JsonResponse
    {
        return $this->ok($this->reports->salaries($request->filters()));
    }

    /** Ko'nikmalar bo'yicha o'zlashtirish (daraja/guruh kesimida) */
    public function skills(SkillsReportRequest $request): JsonResponse
    {
        return $this->ok($this->reports->skills($request->filters()));
    }

    /** Oylik o'zlashtirish hisobotlari (ko'rish / tuzish) */
    public function monthlyProgress(MonthlyReportRequest $request, MonthlyReportService $reports): JsonResponse
    {
        $data = $request->validated();
        $year = (int) ($data['year'] ?? now()->year);
        $month = (int) ($data['month'] ?? now()->month);

        $students = Student::active()
            ->when($data['student_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->when($data['class_id'] ?? null, fn ($q, $id) => $q->where('class_id', $id))
            ->get();

        $items = $students->map(fn ($s) => $reports->build($s, $year, $month, (bool) ($data['with_ai'] ?? false))->load('student'));

        return $this->ok(MonthlyReportResource::collection($items));
    }

    /** Oylik hisobotlarni ota-onalarga (Telegram) yuborish */
    public function sendMonthlyProgress(MonthlyReportRequest $request, MonthlyReportService $reports): JsonResponse
    {
        $data = $request->validated();
        $year = (int) ($data['year'] ?? now()->year);
        $month = (int) ($data['month'] ?? now()->month);

        if (! empty($data['student_id'])) {
            $report = $reports->build(Student::findOrFail($data['student_id']), $year, $month, (bool) ($data['with_ai'] ?? true));
            $reports->send($report);

            return $this->ok(new MonthlyReportResource($report->fresh('student')), 'Hisobot yuborildi.');
        }

        $count = $reports->dispatchAll($year, $month, (bool) ($data['with_ai'] ?? true));

        return $this->ok(['sent' => $count], "{$count} ta hisobot navbatga qo'yildi.");
    }
}
