<?php

namespace App\Console\Commands;

use App\Services\PermissionService;
use Illuminate\Console\Command;

class UpdatePermissions extends Command
{
    protected $signature = 'permissions:sync {--prune : Route'."'".'da yo'."'".'q permissionlarni o'."'".'chirish}';

    protected $description = 'Route\'lardan permission\'larni generatsiya qiladi (mavjudlari saqlanadi)';

    public function handle(PermissionService $service): int
    {
        $result = $service->syncFromRoutes(prune: (bool) $this->option('prune'));

        $this->info("✅ {$result['created']} ta permission yaratildi");
        $this->info("♻️  {$result['updated']} ta permission yangilandi");
        $this->info("🗑  {$result['deleted']} ta permission o'chirildi");
        $this->line("📊 Jami: {$result['total']}");

        return self::SUCCESS;
    }
}
