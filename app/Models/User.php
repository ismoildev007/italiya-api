<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, \Laravel\Sanctum\HasApiTokens;

    protected $auditInclude = [
        'firstname',
        'lastname',
        'username',
        'project_permission',
        'password',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
    ];

    /**
     * Disable strict auditing locally so hidden fields like password can be recorded.
     *
     * @var bool
     */
    protected $auditStrict = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'username',
        'project_permission',
        'password',
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'project_permission' => 'array',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_name', 'role_name', 'username', 'name')
            ->using(RoleUser::class);
    }

    public function hasRole($role)
    {
        $roles = is_array($role) ? $role : [$role];
        return $this->roles()->whereIn('roles.name', $roles)->exists();
    }

    public function hasPermission($permissionName)
    {
        return $this->roles()->whereHas('permissions', function($q) use ($permissionName) {
            $q->where('permissions.name', $permissionName);
        })->exists();
    }
}
