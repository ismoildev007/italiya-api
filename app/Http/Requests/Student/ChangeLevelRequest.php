<?php

namespace App\Http\Requests\Student;

use App\Http\Requests\BaseRequest;
use App\Support\Level;
use Illuminate\Validation\Rule;

class ChangeLevelRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::in(Level::all())],
            'reason' => 'nullable|in:placement,promoted,manual,certificate,demoted',
        ];
    }
}
