<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\BaseRequest;

class SyncPermissionsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'permissions' => 'present|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];
    }
}
