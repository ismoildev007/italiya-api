<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\Blameable;
use App\Models\Concerns\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $firstname
 * @property string $lastname
 * @property string $username
 * @property string $status
 */
class User extends Authenticatable
{
    use BelongsToSchool, Blameable, Filterable, HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'school_id',
        'firstname',
        'lastname',
        'middlename',
        'username',
        'phone',
        'email',
        'password',
        'avatar',
        'locale',
        'settings',
        'status',
        'last_login_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'settings' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Bog'lanishlar */
    /* ------------------------------------------------------------------ */

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_name', 'id', 'name')
            ->using(RoleUser::class);
    }

    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    /* ------------------------------------------------------------------ */
    /* Aksessorlar */
    /* ------------------------------------------------------------------ */

    public function getFullNameAttribute(): string
    {
        return trim("{$this->lastname} {$this->firstname} ".($this->middlename ?? ''));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /* ------------------------------------------------------------------ */
    /* RBAC */
    /* ------------------------------------------------------------------ */

    /**
     * Foydalanuvchining rol nomlari (so'rov davomida keshlanadi).
     *
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        return $this->relationLoaded('roles')
            ? $this->roles->pluck('name')->all()
            : $this->roles()->pluck('roles.name')->all();
    }

    /**
     * @param  string|array<int, string>  $role
     */
    public function hasRole(string|array $role): bool
    {
        $roles = is_array($role) ? $role : [$role];

        return count(array_intersect($roles, $this->roleNames())) > 0;
    }

    public function isSuperadmin(): bool
    {
        return $this->hasRole(config('authorization.super_role', 'superadmin'));
    }

    /**
     * Superadmin barcha cheklovlardan tashqarida.
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->permissionNames()->contains($permission);
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        return once(fn () => Permission::query()
            ->join('permission_role', 'permissions.name', '=', 'permission_role.permission_name')
            ->join('role_user', 'permission_role.role_name', '=', 'role_user.role_name')
            ->where('role_user.user_id', $this->id)
            ->distinct()
            ->pluck('permissions.name'));
    }
}
