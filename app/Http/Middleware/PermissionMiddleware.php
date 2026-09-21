<?php

namespace App\Http\Middleware;

use App\Support\PermissionHelper;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route'ga mos permission'ni tekshiradi.
 * Superadmin barcha cheklovlardan tashqarida.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Avtorizatsiyadan o\'tilmagan.',
            ], 401);
        }

        if (! $user->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Foydalanuvchi bloklangan.',
            ], 403);
        }

        if ($user->isSuperadmin()) {
            return $next($request);
        }

        $route = $request->route();
        $permission = $route ? PermissionHelper::routeToPermissionName($route) : null;

        if (! $permission || in_array($permission, config('authorization.free_routes', []), true)) {
            return $next($request);
        }

        if (! $user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu amal uchun ruxsat yo\'q.',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
