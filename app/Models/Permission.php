<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permission'lar route'lardan avtomatik generatsiya qilinadi
 * (`php artisan permissions:sync`), qo'lda yaratilmaydi.
 *
 * @property string $name
 * @property string $group
 */
class Permission extends Model
{
    use Filterable;

    public $timestamps = false;

    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $hidden = ['pivot'];

    protected $fillable = [
        'name',
        'group',
        'title',
        'method',
        'uri',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role', 'permission_name', 'role_name', 'name', 'name')
            ->using(PermissionRole::class);
    }
}
