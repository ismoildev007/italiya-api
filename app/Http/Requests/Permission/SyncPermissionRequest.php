<?php

namespace App\Http\Requests\Permission;

use App\Http\Requests\BaseRequest;

class SyncPermissionRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['prune' => 'nullable|boolean'];
    }

    public function shouldPrune(): bool
    {
        return $this->boolean('prune');
    }
}
