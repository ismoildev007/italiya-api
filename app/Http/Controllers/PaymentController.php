<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\IndexPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
    ) {}

    public function index(IndexPaymentRequest $request): JsonResponse
    {
        return $this->ok(PaymentResource::collection(
            $this->payments->paginate($request->filters(), $request->perPage(30))
        ));
    }

    /**
     * To'lovni qabul qilish. Chek/screenshot fayl sifatida biriktiriladi.
     * Ortiqcha summa musbat balans bo'lib keyingi oyga o'tadi.
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        return $this->created(
            new PaymentResource($this->payments->register($request->payload(['receipt']), $request->file('receipt'))),
            'To\'lov qabul qilindi.'
        );
    }

    public function show(Payment $payment): JsonResponse
    {
        return $this->ok(new PaymentResource($this->payments->withDetails($payment)));
    }

    /** To'lovni bekor qilish — balans qayta hisoblanadi */
    public function destroy(Payment $payment): JsonResponse
    {
        $this->payments->cancel($payment);

        return $this->deleted('To\'lov bekor qilindi, balans qayta hisoblandi.');
    }
}
