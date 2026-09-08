<?php

namespace App\Console\Commands;

use App\Console\Helpers\PermissionHelper;
use App\Models\Crud;
use App\Models\Permission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\VarDumper\VarDumper;

class UpdatePermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update-permissions';

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
            $countCreatedPermissions = 0;
            $actionPermissions = [];
            $freeControllers = config('authorization.free_controllers', []);
            $freeRoutes = config('authorization.free_routes', []);
            foreach (Route::getRoutes()->getRoutes() as $route) {
                if(! in_array('role', $route->middleware())) {
                    continue;
                }
                if (in_array($route->getControllerClass(), $freeControllers)) continue;
                $permissionName =  PermissionHelper::routeToPermissionName($route);
                if (in_array($permissionName, $freeRoutes)) continue;
                if (! $route->getControllerClass()) continue;
                if (! Permission::where('name', $permissionName)->exists()) {
                    $countCreatedPermissions++;
                    Permission::create(['name' => $permissionName]);
                }
                $actionPermissions[] = $permissionName;
            }
            $countDeletedPermissions = $this->deletePermissions($actionPermissions);
            $this->cacheRefresh();
            DB::connection()->commit();

            $this->info('<fg=green>' . $countCreatedPermissions . ' permission(s) created!</>');
            $this->info('<fg=green>' . $countDeletedPermissions . ' permission(s) deleted!</>');
        } catch (\Throwable $th) {
            DB::connection()->rollBack();
            throw $th;
        }
    }

    protected function cacheRefresh()
    {
        Cache::forget('permissions');
        Cache::put('permissions', Permission::get());
    }

    protected function deletePermissions($actionPermissions)
    {
        $permissions = Permission::all();
        $countDeleted = 0;
        foreach ($permissions as $permission) {
            if (! in_array($permission->name, $actionPermissions)) {
                $permission->delete();
                $countDeleted++;
            }
        }
        return $countDeleted;
    }
}
