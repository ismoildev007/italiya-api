<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasUserRules;

class StoreGuardianRequest extends BaseRequest
{
    use HasUserRules;

    public function rules(): array
    {
        return $this->nameRules() + [
            'phone' => 'required|string|max:32',
            'workplace' => 'nullable|string|max:150',
            'passport_number' => 'nullable|string|max:32',
            'student_id' => 'required|exists:students,id',
            'relation' => 'required|in:father,mother,grandparent,other',
            'is_primary' => 'boolean',
            // Mobil ilova uchun akkaunt
            'create_account' => 'boolean',
            'username' => ['nullable', ...$this->usernameRule()],
            'password' => 'nullable|string|min:6',
        ];
    }

    public function messages(): array
    {
        return $this->usernameMessages();
    }
}
