<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class RoleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'is_system' => (bool) $this->is_system,
            'is_deletable' => ! $this->isProtected(),
            'level' => $this->level,
            'permissions_count' => $this->whenCounted('permissions'),
            'users_count' => $this->whenCounted('users'),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'created_at' => $this->datetime($this->created_at),
        ];
    }
}
