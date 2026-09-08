<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 * @property int $type
 * @property string $description
 */

class Role extends Model
{
    public $timestamps = false;
    protected $primaryKey = 'name';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $hidden = ['pivot'];

    protected $fillable = [
        'name',
        'type',
        'description',
        'created_by',
        'updated_by'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_name', 'user_name', 'name', 'username')
            ->using(RoleUser::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role', 'role_name', 'permission_name', 'name', 'name')
            ->using(PermissionRole::class);
    }
}
