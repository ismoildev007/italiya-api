<?php

namespace App\Http\Requests\Role;

use App\Http\Requests\BaseRequest;

class StoreRoleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            // Texnik nom — inglizcha, kichik harflar bilan
            'name' => 'required|string|max:64|regex:/^[a-z][a-z0-9_]*$/|unique:roles,name',
            'title' => 'required|string|max:150',
            'description' => 'nullable|string|max:255',
            'level' => 'nullable|integer|min:1|max:999',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'Rol nomi faqat lotin kichik harflari, raqam va pastki chiziqdan iborat bo\'lishi kerak (masalan: head_teacher).',
        ];
    }
}
