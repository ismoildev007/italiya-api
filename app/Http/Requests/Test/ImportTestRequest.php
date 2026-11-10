<?php

namespace App\Http\Requests\Test;

use App\Http\Requests\BaseRequest;

/**
 * Excel/CSV dan test savollarini o'qish (bazaga yozilmaydi):
 * `№ | Savol | A | B | C | D | To'g'ri javob | Izoh | Ball` yoki lug'at formati (`so'z | tarjima`).
 */
class ImportTestRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,tsv,xlsx|max:10240',
            // lug'at formatida nechta savol tuzilsin
            'question_count' => 'nullable|integer|min:2|max:100',
        ];
    }
}
