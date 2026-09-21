<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\BaseRequest;

class TransferStudentRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'class_id' => 'required|exists:school_classes,id',
            'reason' => 'nullable|in:promoted,transferred,enrolled',
        ];
    }

    public function reason(): string
    {
        return $this->input('reason', 'transferred');
    }
}
