<?php

namespace App\Console\Commands;

use App\Support\PermissionHelper;
use Illuminate\Console\Command;

class RouteList extends Command
{
    protected $signature = 'permissions:list';

    protected $description = 'Route → permission moslik jadvalini ko\'rsatadi';

    public function handle(): int
    {
        $rows = collect(PermissionHelper::collectFromRoutes())
            ->map(fn ($p) => [$p['group'], $p['name'], $p['method'], '/'.ltrim($p['uri'], '/')])
            ->all();

        $this->table(['Guruh', 'Permission', 'Method', 'URI'], $rows);
        $this->info('Jami: '.count($rows));

        return self::SUCCESS;
    }
}
