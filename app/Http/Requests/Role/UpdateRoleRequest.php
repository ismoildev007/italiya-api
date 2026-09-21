<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends BaseRequest
{
    public function rules(): array
    {
        $roleName = $this->route('role')?->name;

        return [
            'name' => ['sometimes', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'name')->ignore($roleName, 'name')],
            'title' => 'sometimes|required|string|max:150',
            'description' => 'nullable|string|max:255',
            'level' => 'nullable|integer|min:1|max:999',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];
    }
}
