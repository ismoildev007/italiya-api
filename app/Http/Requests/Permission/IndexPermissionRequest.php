<?php

namespace App\Http\Requests\Permission;

use App\Http\Requests\BaseRequest;

class IndexPermissionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'group' => 'nullable|string|max:64',
        ];
    }
}
