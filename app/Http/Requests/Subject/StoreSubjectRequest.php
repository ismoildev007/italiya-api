<?php

namespace App\Http\Requests\Subject;

use App\Http\Requests\BaseRequest;

class StoreSubjectRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150|unique:subjects,name',
            'code' => 'nullable|string|max:32',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
