<?php

namespace App\Http\Requests\Invoice;

use App\Http\Requests\BaseRequest;

class UpdateInvoiceRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'due_date' => 'nullable|date',
            'note' => 'nullable|string|max:255',
            'status' => 'nullable|in:unpaid,partial,paid,canceled',
        ];
    }
}
