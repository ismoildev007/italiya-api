<?php

namespace App\Http\Controllers;

use App\Http\Requests\Salary\GenerateMonthlySalaryRequest;
use App\Http\Requests\Salary\IndexSalaryRequest;
use App\Http\Requests\Salary\MarkPaidRequest;
use App\Http\Requests\Salary\PreviewSalaryRequest;
use App\Http\Requests\Salary\StoreSalaryRequest;
use App\Http\Requests\Salary\UpdateSalaryRequest;
use App\Http\Resources\TeacherSalaryResource;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Services\SalaryService;
use Illuminate\Http\JsonResponse;

class TeacherSalaryController extends Controller
{
    public function __construct(
        private readonly SalaryService $salaries,
    ) {}

    public function index(IndexSalaryRequest $request): JsonResponse
    {
        return $this->ok(TeacherSalaryResource::collection(
            $this->salaries->paginate($request->filters(), $request->perPage(30))
        ));
    }

    /** Oldindan hisoblash (saqlamasdan) */
    public function preview(PreviewSalaryRequest $request, Teacher $teacher): JsonResponse
    {
        $data = $request->validated();

        return $this->ok($this->salaries->calculate(
            $teacher,
            $data['year'],
            $data['month'],
            (float) ($data['bonus'] ?? 0),
            (float) ($data['deduction'] ?? 0),
        ));
    }

    /** Bitta o'qituvchi uchun hisoblash va saqlash */
    public function store(StoreSalaryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $salary = $this->salaries->generate(
            Teacher::findOrFail($data['teacher_id']),
            $data['year'],
            $data['month'],
            (float) ($data['bonus'] ?? 0),
            (float) ($data['deduction'] ?? 0),
        );

        return $this->created(new TeacherSalaryResource($salary->load('teacher.user')), 'Ish haqi hisoblandi.');
    }

    /** Barcha o'qituvchilar uchun oylik hisoblash */
    public function generateMonthly(GenerateMonthlySalaryRequest $request): JsonResponse
    {
        ['year' => $year, 'month' => $month] = $request->validated();

        $count = $this->salaries->generateMonthly($year, $month);

        return $this->ok(['generated' => $count], "{$count} ta o'qituvchi uchun ish haqi hisoblandi.");
    }

    public function show(TeacherSalary $salary): JsonResponse
    {
        return $this->ok(new TeacherSalaryResource($salary->load('teacher.user')));
    }

    public function update(UpdateSalaryRequest $request, TeacherSalary $salary): JsonResponse
    {
        return $this->updated(
            new TeacherSalaryResource($this->salaries->update($salary, $request->validated())),
            'Yangilandi.'
        );
    }

    /** To'langan deb belgilash */
    public function markPaid(MarkPaidRequest $request, TeacherSalary $salary): JsonResponse
    {
        return $this->updated(
            new TeacherSalaryResource($this->salaries->markPaid($salary, $request->input('note'))->load('teacher.user')),
            'To\'langan deb belgilandi.'
        );
    }
}
