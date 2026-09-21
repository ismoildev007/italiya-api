<?php

namespace App\Http\Requests\Guardian;

use App\Http\Requests\BaseRequest;
use App\Http\Requests\Concerns\HasUserRules;

class UpdateGuardianRequest extends BaseRequest
{
    use HasUserRules;

    public function rules(): array
    {
        return $this->nameRules('sometimes') + [
            'phone' => 'sometimes|string|max:32',
            'workplace' => 'nullable|string|max:150',
            'passport_number' => 'nullable|string|max:32',
        ];
    }
}
