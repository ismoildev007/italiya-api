<?php

namespace App\Http\Requests\Notification;

use App\Http\Requests\BaseRequest;

class MarkFailedRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['error' => 'nullable|string|max:1000'];
    }
}
