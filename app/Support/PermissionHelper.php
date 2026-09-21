<?php

namespace App\Support;

use Illuminate\Routing\Route as RouteInstance;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Permission nomlari route'lardan avtomatik hosil qilinadi:
 * `StudentController@index` → `student-index`
 */
class PermissionHelper
{
    public static function routeToPermissionName(RouteInstance $route): ?string
    {
        $controllerClass = $route->getControllerClass();

        if (! $controllerClass) {
            return null;
        }

        $controller = Str::replace('Controller', '', class_basename($controllerClass));

        return Str::snake($controller).'-'.Str::snake($route->getActionMethod());
    }

    public static function routeToGroup(RouteInstance $route): ?string
    {
        $controllerClass = $route->getControllerClass();

        if (! $controllerClass) {
            return null;
        }

        return Str::snake(Str::replace('Controller', '', class_basename($controllerClass)));
    }

    /**
     * Permission talab qiladigan barcha route'lar ro'yxati.
     *
     * @return array<int, array{name: string, group: string, title: string, method: string, uri: string}>
     */
    public static function collectFromRoutes(): array
    {
        $freeControllers = config('authorization.free_controllers', []);
        $freeRoutes = config('authorization.free_routes', []);
        $permissions = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $controllerClass = $route->getControllerClass();

            if (! $controllerClass) {
                continue;
            }

            // Faqat `permission` middleware bilan himoyalangan route'lar
            if (! in_array('permission', $route->gatherMiddleware(), true)) {
                continue;
            }

            if (in_array($controllerClass, $freeControllers, true)) {
                continue;
            }

            $name = self::routeToPermissionName($route);

            if (! $name || in_array($name, $freeRoutes, true)) {
                continue;
            }

            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD') ?? 'GET';

            $permissions[$name] = [
                'name' => $name,
                'group' => self::routeToGroup($route),
                'title' => $method.' /'.ltrim($route->uri(), '/'),
                'method' => $method,
                'uri' => $route->uri(),
            ];
        }

        ksort($permissions);

        return array_values($permissions);
    }
}
