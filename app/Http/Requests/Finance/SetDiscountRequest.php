<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Validator;

class SetDiscountRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'type' => 'required|in:percent,amount',
            'value' => 'required|numeric|min:0',
            'reason' => 'nullable|string|max:255',
            'starts_on' => 'nullable|date',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('type') === 'percent' && (float) $this->input('value') > 100) {
                    $validator->errors()->add('value', 'Foizli chegirma 100 dan oshmasligi kerak.');
                }
            },
        ];
    }
}
