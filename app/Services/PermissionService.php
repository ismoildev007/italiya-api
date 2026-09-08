<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Pagination\LengthAwarePaginator;

class PermissionService
{
    public function getFilteredPermissions(array $filters): LengthAwarePaginator
    {
        $query = Permission::query();

        if (!empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('name')->paginate(50)->withQueryString();
    }
}
