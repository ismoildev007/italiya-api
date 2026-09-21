<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\BaseRequest;

class IndexRoleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:100',
            'is_system' => 'nullable|boolean',
            'permission' => 'nullable|string|exists:permissions,name',
        ];
    }
}
