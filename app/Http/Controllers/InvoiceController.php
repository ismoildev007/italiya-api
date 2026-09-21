<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invoice\GenerateMonthlyRequest;
use App\Http\Requests\Invoice\IndexInvoiceRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
    ) {}

    public function index(IndexInvoiceRequest $request): JsonResponse
    {
        return $this->ok(InvoiceResource::collection(
            $this->invoices->paginate($request->filters(), $request->perPage(30))
        ));
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return $this->ok(new InvoiceResource($this->invoices->withDetails($invoice)));
    }

    /** Bitta o'quvchi uchun hisob-faktura chiqarish */
    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        return $this->created(
            new InvoiceResource($this->invoices->generateFor($request->validated())),
            'Hisob-faktura yaratildi.'
        );
    }

    /** Oylik hisob-fakturalarni ommaviy generatsiya qilish */
    public function generateMonthly(GenerateMonthlyRequest $request): JsonResponse
    {
        ['year' => $year, 'month' => $month] = $request->validated();

        $count = $this->invoices->generateMonthly($year, $month);

        return $this->ok(['generated' => $count], "{$count} ta hisob-faktura generatsiya qilindi.");
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        return $this->updated(
            new InvoiceResource($this->invoices->update($invoice, $request->validated())),
            'Hisob-faktura yangilandi.'
        );
    }
}
