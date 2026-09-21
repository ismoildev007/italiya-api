<?php

namespace App\Console\Commands;

use App\Services\PermissionService;
use Illuminate\Console\Command;

class RefreshPermissions extends Command
{
    protected $signature = 'permissions:refresh';

    protected $description = 'Permission\'larni route\'lar bo\'yicha to\'liq qayta quradi (keraksizlarini o\'chiradi)';

    public function handle(PermissionService $service): int
    {
        $result = $service->syncFromRoutes(prune: true);

        $this->info("✅ {$result['created']} yaratildi, {$result['deleted']} o'chirildi, jami {$result['total']}");

        return self::SUCCESS;
    }
}
