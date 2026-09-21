<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PermissionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'group' => $this->group,
            'title' => $this->title,
            'method' => $this->method,
            'uri' => $this->uri,
        ];
    }
}
