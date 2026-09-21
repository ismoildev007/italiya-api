<?php

namespace App\Http\Controllers;

use App\Http\Requests\Student\ChangeLevelRequest;
use App\Http\Requests\Student\IndexStudentRequest;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\StudentHistoryRequest;
use App\Http\Requests\Student\TransferStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Resources\FinanceTransactionResource;
use App\Http\Resources\GradeResource;
use App\Http\Resources\StudentResource;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\GradeService;
use App\Services\StudentService;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentService $students,
    ) {}

    public function index(IndexStudentRequest $request): JsonResponse
    {
        return $this->ok(StudentResource::collection(
            $this->students->paginate($request->filters(), $request->perPage())
        ));
    }

    public function store(StoreStudentRequest $request): JsonResponse
    {
        return $this->created(
            new StudentResource($this->students->create($request->validated())),
            'O\'quvchi qo\'shildi.'
        );
    }

    public function show(Student $student): JsonResponse
    {
        return $this->ok(new StudentResource($this->students->withDetails($student)));
    }

    public function update(UpdateStudentRequest $request, Student $student): JsonResponse
    {
        return $this->updated(
            new StudentResource($this->students->update($student, $request->validated())),
            'O\'quvchi ma\'lumotlari yangilandi.'
        );
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->students->delete($student);

        return $this->deleted('O\'quvchi arxivlandi.');
    }

    /** Boshqa sinfga ko'chirish — eski yozuv arxivlanadi */
    public function transfer(TransferStudentRequest $request, Student $student): JsonResponse
    {
        $class = SchoolClass::findOrFail($request->validated()['class_id']);

        return $this->updated(
            new StudentResource($this->students->transfer($student, $class, $request->reason())),
            "O'quvchi {$class->name} sinfiga ko'chirildi."
        );
    }

    /** Darajani o'zgartirish (A1 → A2 …) — tarix yoziladi, xabar yuboriladi */
    public function changeLevel(ChangeLevelRequest $request, Student $student): JsonResponse
    {
        $data = $request->validated();

        return $this->updated(
            new StudentResource($this->students->changeLevel($student, $data['level'], $data['reason'] ?? 'manual')),
            "Daraja {$data['level']} ga o'zgartirildi."
        );
    }

    /** Qurilma bog'lanishini tozalash (o'quvchi boshqa telefondan kira oladi) */
    public function resetDevice(Student $student): JsonResponse
    {
        return $this->updated(new StudentResource($this->students->resetDevice($student)), 'Qurilma bog\'lanishi tozalandi.');
    }

    /** Daraja tarixi */
    public function levelHistory(Student $student): JsonResponse
    {
        return $this->ok($student->levelHistory()->orderByDesc('changed_at')->get());
    }

    /** Sinf tarixi (arxiv) */
    public function classHistory(Student $student): JsonResponse
    {
        return $this->ok($this->students->classHistory($student));
    }

    /** O'quvchining davomat tarixi */
    public function attendance(StudentHistoryRequest $request, Student $student, AttendanceService $attendance): JsonResponse
    {
        return $this->ok($attendance->studentHistory($student, ...$request->periodRange()));
    }

    /** O'quvchining baholari */
    public function grades(StudentHistoryRequest $request, Student $student, GradeService $grades): JsonResponse
    {
        return $this->ok(GradeResource::collection($grades->forStudent($student->id, $request->filters())));
    }

    /** To'liq moliyaviy tarix — hech narsa yashirin qolmaydi */
    public function financeHistory(Student $student): JsonResponse
    {
        $history = $this->students->financeHistory($student);

        return $this->ok([
            'balance' => $history['balance'],
            'transactions' => FinanceTransactionResource::collection($history['transactions']),
        ]);
    }
}
