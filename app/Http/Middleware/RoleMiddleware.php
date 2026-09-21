<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aniq rollarni talab qiladi: `->middleware('role:admin,accountant')`.
 * Superadmin doim o'tadi.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Avtorizatsiyadan o\'tilmagan.'], 401);
        }

        if ($user->isSuperadmin() || $user->hasRole($roles)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Bu bo\'lim uchun ruxsat yo\'q.',
        ], 403);
    }
}
