<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'name';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $hidden = ['pivot'];

    protected $fillable = [
        'name'
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'permission_role', 'permission_name', 'role_name', 'name', 'name')
            ->using(PermissionRole::class);
    }
}
