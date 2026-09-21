<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;

class ResetPasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['password' => 'required|string|min:6'];
    }
}
