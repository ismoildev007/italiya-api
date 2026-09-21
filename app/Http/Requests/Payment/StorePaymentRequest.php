<?php

namespace App\Http\Requests\Payment;

use App\Http\Requests\BaseRequest;

class StorePaymentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'amount' => 'required|numeric|min:1',
            'method' => 'required|in:cash,card,transfer,other',
            'paid_at' => 'nullable|date',
            'note' => 'nullable|string|max:255',
            // Chek / screenshot
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ];
    }
}
