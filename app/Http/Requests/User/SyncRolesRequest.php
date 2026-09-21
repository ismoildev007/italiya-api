<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;

class SyncRolesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'roles' => 'present|array',
            'roles.*' => 'string|exists:roles,name',
        ];
    }
}
