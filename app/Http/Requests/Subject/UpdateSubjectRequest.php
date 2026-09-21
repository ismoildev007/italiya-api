<?php

namespace App\Http\Requests\Subject;

use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;

class UpdateSubjectRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150', Rule::unique('subjects', 'name')->ignore($this->route('subject')?->id)],
            'code' => 'nullable|string|max:32',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ];
    }
}
