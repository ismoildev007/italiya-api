<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\LocaleMiddleware::class,
            \App\Http\Middleware\PageViewAudit::class
        ]);
        $middleware->api(append: [
            \App\Http\Middleware\PageViewAudit::class
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        $middleware->redirectTo(function ($request) {
            $locale = $request->segment(1);
            if (!in_array($locale, ['uz', 'ru', 'en'])) {
                $locale = 'uz';
            }
            return route('login', ['locale' => $locale]);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation errors',
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated or Invalid Token.',
                ], 401);
            }
        });
    })->create();
