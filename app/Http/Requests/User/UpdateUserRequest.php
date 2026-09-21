<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasUserRules;

class UpdateUserRequest extends BaseRequest
{
    use HasUserRules;

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return $this->nameRules('sometimes|required') + [
            'username' => ['sometimes', 'required', ...$this->usernameRule($userId)],
            'phone' => $this->phoneRule($userId),
            'email' => $this->emailRule($userId),
            'password' => 'nullable|string|min:6',
            'locale' => 'nullable|in:uz,ru,en',
            'status' => 'nullable|in:active,blocked',
            'roles' => 'sometimes|array|min:1',
            'roles.*' => 'string|exists:roles,name',
        ];
    }

    public function messages(): array
    {
        return $this->usernameMessages();
    }
}
