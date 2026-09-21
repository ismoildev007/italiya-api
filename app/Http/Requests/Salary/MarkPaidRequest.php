<?php

namespace App\Http\Requests\Salary;

use App\Http\Requests\BaseRequest;

class MarkPaidRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['note' => 'nullable|string|max:255'];
    }
}
