<?php

namespace App\Http\Controllers;

use App\Http\Requests\Student\StudentHistoryRequest;
use App\Models\Student;
use App\Models\TestAttempt;
use App\Services\PortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O'quvchi va ota-ona kabineti — FAQAT O'QISH huquqi.
 * O'quvchi faqat o'z ma'lumotlarini, ota-ona faqat farzandiniki ko'radi.
 */
class PortalController extends Controller
{
    public function __construct(
        private readonly PortalService $portal,
    ) {}

    /** Foydalanuvchiga ochiq bo'lgan o'quvchilar (o'zi yoki farzandlari) */
    public function children(Request $request): JsonResponse
    {
        return $this->ok($this->portal->children($request->user()));
    }

    public function profile(Request $request, Student $student): JsonResponse
    {
        return $this->ok($this->portal->profile(
            $this->portal->authorizeStudent($request->user(), $student)
        ));
    }

    public function attendance(StudentHistoryRequest $request, Student $student): JsonResponse
    {
        return $this->ok($this->portal->attendance(
            $this->portal->authorizeStudent($request->user(), $student),
            ...$request->periodRange()
        ));
    }

    public function grades(StudentHistoryRequest $request, Student $student): JsonResponse
    {
        return $this->ok($this->portal->grades(
            $this->portal->authorizeStudent($request->user(), $student),
            $request->filters()
        ));
    }

    public function tests(Request $request, Student $student): JsonResponse
    {
        return $this->ok($this->portal->tests(
            $this->portal->authorizeStudent($request->user(), $student)
        ));
    }

    /** Topshirilgan testdagi o'z javoblarini ko'rish */
    public function testAttempt(Request $request, Student $student, TestAttempt $attempt): JsonResponse
    {
        return $this->ok($this->portal->attemptDetails(
            $this->portal->authorizeStudent($request->user(), $student),
            $attempt
        ));
    }

    public function payments(Request $request, Student $student): JsonResponse
    {
        return $this->ok($this->portal->payments(
            $this->portal->authorizeStudent($request->user(), $student)
        ));
    }
}
