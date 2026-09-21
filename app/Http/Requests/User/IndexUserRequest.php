<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;

class IndexUserRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->paginationRules() + [
            'search' => 'nullable|string|max:100',
            'role' => 'nullable|string|exists:roles,name',
            'status' => 'nullable|in:active,blocked',
            'permission' => 'nullable|string|exists:permissions,name',
        ];
    }
}
