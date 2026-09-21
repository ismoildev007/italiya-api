<?php

namespace App\Http\Requests\Vocab;

use App\Http\Requests\BaseRequest;

/** Excel/CSV: so'z | tarjima (uz) | en | ru | misol | kategoriya */
class ImportDeckRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt,tsv,xlsx|max:10240',
        ];
    }
}
