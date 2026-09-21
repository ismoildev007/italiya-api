<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UserResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'middlename' => $this->middlename,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'phone' => $this->phone,
            'email' => $this->email,
            'avatar' => $this->avatar,
            'locale' => $this->locale,
            'settings' => $this->settings ?? ['notifications' => true, 'sound' => true, 'theme' => 'auto', 'ui_language' => 'uz'],
            'status' => $this->status,
            'last_login_at' => $this->datetime($this->last_login_at),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'role_names' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'created_at' => $this->datetime($this->created_at),
            'updated_at' => $this->datetime($this->updated_at),
        ];
    }
}
