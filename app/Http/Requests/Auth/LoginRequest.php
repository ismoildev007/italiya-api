<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseRequest;

class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'login' => 'required|string|max:100',
            'password' => 'required|string',
            'device' => 'nullable|string|max:100',
            'device_id' => 'nullable|string|max:128',
        ];
    }

    protected function prepareForValidation(): void
    {
        // `username` nomi bilan yuborilsa ham qabul qilamiz
        if (! $this->has('login') && $this->has('username')) {
            $this->merge(['login' => $this->input('username')]);
        }
    }

    public function attributes(): array
    {
        return ['login' => 'Login'];
    }
}
