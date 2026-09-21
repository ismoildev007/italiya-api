<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\AssignTariffRequest;
use App\Http\Requests\Finance\DebtorsRequest;
use App\Http\Requests\Finance\IndexTransactionRequest;
use App\Http\Requests\Finance\SetDiscountRequest;
use App\Http\Resources\FinanceTransactionResource;
use App\Http\Resources\StudentDiscountResource;
use App\Http\Resources\StudentTariffResource;
use App\Models\Student;
use App\Services\FinanceService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

/**
 * O'quvchi moliyasi: tarif, individual chegirma, balans va tranzaksiya tarixi.
 */
class FinanceController extends Controller
{
    public function __construct(
        private readonly FinanceService $finance,
    ) {}

    /** O'quvchiga tarif biriktirish */
    public function assignTariff(AssignTariffRequest $request, Student $student): JsonResponse
    {
        $studentTariff = $this->finance->assignTariffFromInput($student, $request->validated());

        return $this->updated(new StudentTariffResource($studentTariff->load('tariff')), 'Tarif biriktirildi.');
    }

    /**
     * Individual chegirma — faqat shu o'quvchiga tegishli,
     * boshqa o'quvchilarga ta'sir qilmaydi.
     */
    public function setDiscount(SetDiscountRequest $request, Student $student): JsonResponse
    {
        $discount = $this->finance->setDiscountFromInput($student, $request->validated());

        return $this->updated(new StudentDiscountResource($discount), 'Chegirma o\'rnatildi.');
    }

    /** Chegirmani bekor qilish */
    public function removeDiscount(Student $student): JsonResponse
    {
        $this->finance->removeDiscount($student);

        return $this->deleted('Chegirma bekor qilindi.');
    }

    /** Joriy oylik summa: tarif − chegirma */
    public function monthlyAmount(Student $student): JsonResponse
    {
        return $this->ok($this->finance->monthlyAmountSummary($student));
    }

    /** Balans holati */
    public function balance(Student $student): JsonResponse
    {
        return $this->ok($this->finance->balanceSummary($student));
    }

    public function transactions(IndexTransactionRequest $request, Student $student): JsonResponse
    {
        return $this->ok(FinanceTransactionResource::collection(
            $this->finance->transactions($student, $request->filters(), $request->perPage(50))
        ));
    }

    /** Qarzdorlar ro'yxati */
    public function debtors(DebtorsRequest $request, ReportService $reports): JsonResponse
    {
        return $this->ok($reports->debtors($request->validated()));
    }
}
