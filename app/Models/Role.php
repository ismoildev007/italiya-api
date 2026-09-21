<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use App\Models\Concerns\Blameable;
use App\Models\Concerns\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $name
 * @property string $title
 * @property bool $is_system
 */
class Role extends Model
{
    use BelongsToSchool, Blameable, Filterable, HasFactory;

    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['pivot'];

    protected $fillable = [
        'school_id',
        'name',
        'title',
        'description',
        'is_system',
        'level',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'level' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Tizim rollarini hech qanday yo'l bilan o'chirib bo'lmaydi
        static::deleting(function (Role $role) {
            if ($role->isProtected()) {
                abort(403, "«{$role->name}» tizim roli — uni o'chirish mumkin emas.");
            }
        });

        // Tizim rolining texnik nomi (`name`) o'zgartirilmaydi
        static::updating(function (Role $role) {
            if ($role->isProtected() && $role->isDirty('name')) {
                abort(403, "Tizim rolining nomini o'zgartirib bo'lmaydi.");
            }
        });
    }

    public function isProtected(): bool
    {
        return $this->is_system
            || in_array($this->name, config('authorization.system_roles', []), true);
    }

    public function isSuper(): bool
    {
        return $this->name === config('authorization.super_role', 'superadmin');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_name', 'user_id', 'name', 'id')
            ->using(RoleUser::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role', 'role_name', 'permission_name', 'name', 'name')
            ->using(PermissionRole::class);
    }
}
