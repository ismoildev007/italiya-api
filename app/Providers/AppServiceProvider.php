<?php

namespace App\Providers;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Model bog'langan barcha route parametrlari faqat raqam bo'lishi shart —
     * `/students/undefined` kabi so'rovlar SQL xatosi o'rniga toza 404 qaytaradi.
     */
    public const NUMERIC_ROUTE_PARAMS = [
        'user', 'academicYear', 'subject', 'teacher', 'schoolClass', 'student', 'guardian', 'tariff',
        'invoice', 'payment', 'attendance', 'lesson', 'grade', 'test', 'assignment', 'attempt', 'salary', 'event',
        'unit', 'material', 'deck', 'game', 'session', 'homework', 'submission', 'meeting', 'certificate', 'section', 'question', 'lesson', 'duel',
    ];

    public function register(): void
    {
        foreach (self::NUMERIC_ROUTE_PARAMS as $param) {
            Route::pattern($param, '[0-9]+');
        }
    }

    public function boot(): void
    {
        Model::preventLazyLoading((bool) env('APP_PREVENT_LAZY_LOADING', false));
        Schema::defaultStringLength(191);

        // Superadmin barcha cheklovlardan tashqarida
        Gate::before(function ($user) {
            return $user->isSuperadmin() ? true : null;
        });

        // Har bir permission uchun Gate — `$user->can('student-index')`
        Gate::define('*', fn () => false);

        $this->app->booted(function () {
            if (! app()->runningInConsole() || app()->runningUnitTests()) {
                $this->defineGates();
            }
        });
    }

    private function defineGates(): void
    {
        try {
            if (! Schema::hasTable('permissions')) {
                return;
            }

            foreach (Permission::pluck('name') as $name) {
                Gate::define($name, fn ($user) => $user->hasPermission($name));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
