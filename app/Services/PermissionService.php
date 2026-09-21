<?php

namespace App\Services;

use App\Models\Permission;
use App\Support\PermissionHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /**
     * Route'lardan permission'larni generatsiya qiladi.
     *
     * @return array{created:int, updated:int, deleted:int, total:int}
     */
    public function syncFromRoutes(bool $prune = false): array
    {
        $discovered = collect(PermissionHelper::collectFromRoutes());
        $existing = Permission::pluck('name')->all();

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($discovered, $existing, &$created, &$updated) {
            foreach ($discovered as $permission) {
                if (in_array($permission['name'], $existing, true)) {
                    Permission::where('name', $permission['name'])->update($permission);
                    $updated++;
                } else {
                    Permission::create($permission);
                    $created++;
                }
            }
        });

        $deleted = 0;

        if ($prune) {
            $keep = $discovered->pluck('name')->all();
            $deleted = Permission::whereNotIn('name', $keep ?: ['__none__'])->delete();
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'total' => Permission::count(),
        ];
    }

    /**
     * @return Collection<int, Permission>
     */
    public function search(array $filters): Collection
    {
        return Permission::query()
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            }))
            ->when($filters['group'] ?? null, fn ($q, $group) => $q->where('group', $group))
            ->orderBy('group')
            ->orderBy('name')
            ->get();
    }

    /**
     * Permission'lar guruhlar bo'yicha — UI'da checkbox daraxti uchun qulay.
     */
    public function grouped(): array
    {
        return Permission::orderBy('group')->orderBy('name')->get()
            ->groupBy('group')
            ->map(fn ($items, $group) => [
                'group' => $group,
                'permissions' => $items->map(fn ($p) => [
                    'name' => $p->name,
                    'title' => $p->title,
                    'method' => $p->method,
                    'uri' => $p->uri,
                ])->values(),
            ])
            ->values()
            ->all();
    }
}
