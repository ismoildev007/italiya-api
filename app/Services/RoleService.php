<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoleService
{
    /**
     * @return Collection<int, Role>
     */
    public function search(array $filters): Collection
    {
        return Role::query()
            ->withCount(['permissions', 'users'])
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when(isset($filters['is_system']), fn ($q) => $q->where('is_system', (bool) $filters['is_system']))
            ->when($filters['permission'] ?? null, fn ($q, $permission) => $q->whereHas(
                'permissions', fn ($q) => $q->where('permissions.name', $permission)
            ))
            // Superadmin rolini faqat superadmin ko'radi
            ->unless(auth()->user()?->isSuperadmin(), fn ($q) => $q->where('name', '!=', config('authorization.super_role')))
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    public function withDetails(Role $role): Role
    {
        return $role->load('permissions')->loadCount('users');
    }

    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'level' => $data['level'] ?? 100,
                // Yangi rollar hech qachon tizim roli bo'lmaydi
                'is_system' => false,
            ]);

            if (! empty($data['permissions'])) {
                $this->syncPermissions($role, $data['permissions']);
            }

            return $role->loadCount(['permissions', 'users']);
        });
    }

    public function update(Role $role, array $data): Role
    {
        $this->guardSuperRole($role);

        // Tizim rolining texnik nomi o'zgarmaydi, lekin ko'rinadigan nomi tahrirlanadi
        $payload = [
            'title' => $data['title'] ?? $role->title,
            'description' => $data['description'] ?? $role->description,
            'level' => $data['level'] ?? $role->level,
        ];

        if (! $role->isProtected() && ! empty($data['name'])) {
            $payload['name'] = $data['name'];
        }

        $role->update($payload);

        if (array_key_exists('permissions', $data)) {
            $this->syncPermissions($role, $data['permissions'] ?? []);
        }

        return $role->fresh()->loadCount(['permissions', 'users']);
    }

    public function delete(Role $role): void
    {
        // Tizim rollarini o'chirib bo'lmaydi (model darajasida ham himoyalangan)
        if ($role->isProtected()) {
            abort(403, "«{$role->title}» tizim roli — uni o'chirish mumkin emas.");
        }

        if ($role->users()->exists()) {
            abort(422, 'Bu rolga biriktirilgan foydalanuvchilar mavjud. Avval ularni boshqa rolga o\'tkazing.');
        }

        $role->delete();
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function syncPermissions(Role $role, array $permissionNames): int
    {
        $this->guardSuperRole($role);

        $valid = Permission::whereIn('name', $permissionNames)->pluck('name')->all();

        $role->permissions()->sync($valid);

        return count($valid);
    }

    public function permissionMatrix(Role $role): array
    {
        return [
            'role' => $role->name,
            'assigned' => $role->permissions()->pluck('permissions.name')->all(),
        ];
    }

    /** Superadmin rolining ruxsatlari o'zgartirilmaydi — u doim hamma joyga kiradi. */
    private function guardSuperRole(Role $role): void
    {
        if ($role->isSuper()) {
            abort(403, 'Superadmin roli o\'zgartirilmaydi — u tizimdagi barcha cheklovlardan tashqarida.');
        }
    }
}
