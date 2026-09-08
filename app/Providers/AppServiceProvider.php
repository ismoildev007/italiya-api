<?php

namespace App\Providers;

use App\Models\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use OwenIt\Auditing\Models\Audit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        try {
            if ($permissions = Cache::rememberForever('permissions', fn() => Permission::all())) {
                foreach ($permissions as $permission) {
                    Gate::define($permission->name, fn($user) => $user->hasPermission($permission));
                }
            }
        } catch (\Exception $e) {
            report($e);
        }
    }
}
