<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Route;

class RoleService
{
    public function getFilteredRoles(array $filters): Collection
    {
        $query = Role::withCount('permissions');

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['permission'])) {
            $query->whereHas('permissions', fn($q) => $q->where('name', $filters['permission']));
        }

        if (auth()->check() && !auth()->user()->hasRole('superadmin')) {
            $query->where('name', '!=', 'superadmin');
        }

        return $query->get();
    }

    public function getAllPermissions(): Collection
    {
        return Permission::orderBy('name')->get();
    }

    public function createRole(array $data): Role
    {
        $data['created_by'] = auth()->id();
        return Role::create($data);
    }

    public function updateRole(Role $role, array $data): bool
    {
        if ($role->name === 'superadmin') {
            abort(403, 'Superadmin rolni tahrirlash mumkin emas.');
        }
        $data['updated_by'] = auth()->id();
        return $role->update($data);
    }

    public function deleteRole(Role $role): bool|null
    {
        if ($role->name === 'superadmin') {
            abort(403, 'Superadmin rolni o\'chirish mumkin emas.');
        }
        return $role->delete();
    }

    public function syncPermissions(Role $role, array $permissionNames): int
    {
        if ($role->name === 'superadmin') {
            abort(403, 'Superadmin ruxsatlarini o\'zgartirish mumkin emas.');
        }

        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        $role->permissions()->detach();
        foreach ($permissionNames as $name) {
            $role->permissions()->attach($name);
        }

        return count($permissionNames);
    }

    public function getPermissionsForRole(Role $role): array
    {
        return [
            'all'      => $this->getAllRoutePermissions(),
            'assigned' => $role->permissions()->pluck('name')->toArray(),
        ];
    }

    private function getAllRoutePermissions(): array
    {
        $routes = Route::getRoutes()->getRoutesByName();
        $names  = [];
        foreach ($routes as $name => $route) {
            if (str_starts_with($name, 'sanctum')
                || str_starts_with($name, 'ignition')
                || str_starts_with($name, 'debugbar')
                || str_starts_with($name, 'web.')
            ) {
                continue;
            }
            $perm = str_replace(['.', '_', ' '], '-', $name);
            $names[] = $perm;
        }
        sort($names);
        return array_values(array_unique($names));
    }
}
