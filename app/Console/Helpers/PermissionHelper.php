<?php

namespace App\Console\Helpers;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;


class PermissionHelper
{
    /**
     * @param \Illuminate\Routing\Route $route
     */
    public static function routeToPermissionName($route)
    {
        $controller = Str::replace('Controller', '', class_basename($route->getControllerClass()));
        return  Str::snake($controller) . '-' . Str::snake($route->getActionMethod());
    }

    public static function getPermissionList()
    {
        $freeControllers = config('authorization.free_controllers', []);
        $freeRoutes = config('authorization.free_routes', []);
        $data = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (in_array($route->getControllerClass(), $freeControllers)) continue;
            $permissionName =  self::routeToPermissionName($route);
            if (in_array($permissionName, $freeRoutes)) continue;
            $data['[' . implode('|', $route->methods()) . '] ' . $route->uri()] = [
                'permission' => $permissionName
            ];
        }
        return $data;
    }
}
