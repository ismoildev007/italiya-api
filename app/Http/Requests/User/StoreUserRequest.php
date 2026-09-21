<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasUserRules;

class StoreUserRequest extends BaseRequest
{
    use HasUserRules;

    public function rules(): array
    {
        return $this->nameRules() + [
            'username' => ['required', ...$this->usernameRule()],
            'phone' => $this->phoneRule(),
            'email' => $this->emailRule(),
            'password' => 'required|string|min:6',
            'locale' => 'nullable|in:uz,ru,en',
            'status' => 'nullable|in:active,blocked',
            'roles' => 'required|array|min:1',
            'roles.*' => 'string|exists:roles,name',
        ];
    }

    public function messages(): array
    {
        return $this->usernameMessages();
    }
}
