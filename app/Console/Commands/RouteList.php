<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class RouteList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:route-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $routes = Route::getRoutes()->getRoutes();
        foreach ($routes as $route) {
            $middlewares = json_encode($route->middleware());
            if (str_contains($middlewares, 'query-param')) {
                $this->info("<fg=yellow>{$route->uri}   <fg=red>" . $middlewares);
                // echo  . $middlewares . "\n";
            }
        }
    }
}
