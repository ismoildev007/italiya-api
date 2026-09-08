<?php

namespace App\Http\Middleware;

use App\Console\Helpers\PermissionHelper;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RoleMiddleware
{

    public function handle(Request $request, Closure $next)
    {
        $currentRoute = Route::getCurrentRoute();
        if (! in_array('role', $currentRoute->middleware())) {
            return $next($request);
        }
        $user = $this->user();

        $route = PermissionHelper::routeToPermissionName($currentRoute);
        if ($user?->hasRole('superadmin')) {
            return $next($request);
        }
        if ($user?->can($route)) {
            return $next($request);
        }
        return response()->json(['message' => 'not_allowed'], 403);
    }

    protected function user(): ?User
    {
        return request()->user();
    }
}
