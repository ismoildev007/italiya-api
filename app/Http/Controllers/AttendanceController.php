<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\BulkAttendanceRequest;
use App\Http\Requests\Attendance\IndexAttendanceRequest;
use App\Http\Requests\Attendance\JournalRequest;
use App\Http\Requests\Attendance\StoreAttendanceRequest;
use App\Http\Requests\Attendance\UpdateAttendanceRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;

/**
 * Davomat. Hozirgi bosqichda faqat QO'LDA belgilanadi (`source = manual`).
 *
 * Tashqi qurilmadan hodisa qabul qiluvchi endpoint (`POST /attendance/event`)
 * bu bosqichda YARATILMAYDI — keyingi fazada qo'shiladi va faqat
 * `source = device` bilan yozuv qo'shadi, mavjud strukturaga tegmaydi.
 */
class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
    ) {}

    public function index(IndexAttendanceRequest $request): JsonResponse
    {
        return $this->ok(AttendanceResource::collection(
            $this->attendance->paginate($request->filters(), $request->perPage(50))
        ));
    }

    /** Sinf jurnali — bir kunlik ro'yxat (tez belgilash interfeysi uchun) */
    public function journal(JournalRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        return $this->ok($this->attendance->classJournal($schoolClass, $request->attendanceDate()));
    }

    /** Jurnalni to'plamli saqlash — butun sinf uchun bitta so'rovda */
    public function bulkStore(BulkAttendanceRequest $request, SchoolClass $schoolClass): JsonResponse
    {
        $result = $this->attendance->bulkMark($schoolClass, $request->attendanceDate(), $request->records());

        return $this->ok($result, "{$result['saved']} ta yozuv saqlandi.");
    }

    /** Bitta o'quvchi davomatini belgilash */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $attendance = $this->attendance->mark(
            Student::findOrFail($data['student_id']),
            $request->attendanceDate(),
            $data['status'],
            $data['note'] ?? null,
        );

        return $this->created(new AttendanceResource($attendance->load('student')), 'Davomat belgilandi.');
    }

    public function show(Attendance $attendance): JsonResponse
    {
        return $this->ok(new AttendanceResource($attendance->load(['student', 'markedBy'])));
    }

    public function update(UpdateAttendanceRequest $request, Attendance $attendance): JsonResponse
    {
        return $this->updated(
            new AttendanceResource($this->attendance->updateRecord($attendance, $request->validated())),
            'Davomat yangilandi.'
        );
    }

    public function destroy(Attendance $attendance): JsonResponse
    {
        $this->attendance->deleteRecord($attendance);

        return $this->deleted('Yozuv o\'chirildi.');
    }
}
