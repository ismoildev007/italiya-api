<?php

namespace App\Http\Requests\User;

use App\Http\Requests\BaseRequest;

class UpdateStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['status' => 'required|in:active,blocked'];
    }
}
