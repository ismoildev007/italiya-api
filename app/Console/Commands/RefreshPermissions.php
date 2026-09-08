<?php

namespace App\Console\Commands;

use App\Console\Helpers\PermissionHelper;
use App\Models\Crud;
use App\Models\Permission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\VarDumper\VarDumper;

class RefreshPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refresh-permissions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update permissions according to the routes';

    /**
     * Permission service instance.
     *
     * @var string
     */
    protected $permissionService;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        DB::connection()->beginTransaction();
        try {
            $freeControllers = config('authorization.free_controllers', []);
            $freeRoutes = config('authorization.free_routes', []);
            $allRoutes = Route::getRoutes()->getRoutes();

            $count = DB::table('permissions')->count();
            $this->clearPermissions();
            $permissions = [];

            foreach ($allRoutes as $route) {
                if (! $route->getControllerClass()) continue;

                if(! in_array('role', $route->middleware())) continue;
                if (in_array($route->getControllerClass(), $freeControllers)) continue;

                $permissionName =  PermissionHelper::routeToPermissionName($route);
                if (in_array($permissionName, $freeRoutes)) continue;

                $permissions[] = ['name' => $permissionName];
                $count--;
            }
            DB::table('permissions')->insert($permissions);
            DB::connection()->commit();
            Cache::put('permissions', Permission::get());
            $this->info('<fg=green>' . ($count < 0 ? abs($count) : 0) . ' permission(s) created!</>');
            $this->info('<fg=green>' . ($count > 0 ? $count : 0) . ' permission(s) deleted!</>');
        } catch (\Throwable $th) {
            DB::connection()->rollBack();
            $this->error($th->getMessage());
        }
    }

    protected function clearPermissions()
    {
        Schema::disableForeignKeyConstraints();
        DB::table('permissions')->truncate();
        Schema::enableForeignKeyConstraints();
    }
}
