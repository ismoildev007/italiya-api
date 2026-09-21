<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;

class DebtorsRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['class_id' => 'nullable|exists:school_classes,id'];
    }
}
