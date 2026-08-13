<?php

namespace App\Providers;

use App\Services\ScopeContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One scope per request: the session-backed company/location selection is
        // read by middleware, controllers and services alike.
        $this->app->singleton(ScopeContext::class);
    }

    public function boot(): void
    {
        // Oracle stores the model attributes we hand it; guarding against typos in
        // fill() is cheap insurance on a schema this wide.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // @can-style directives backed by the application's own permission codes.
        Blade::if('permission', function (string ...$codes) {
            return auth()->check() && auth()->user()->hasAnyPermission(...$codes);
        });

        Blade::if('role', function (string ...$codes) {
            return auth()->check() && auth()->user()->hasRole(...$codes);
        });

        // Unread notification badge is needed by the shared layout on every page.
        View::composer('layouts.app', function ($view) {
            $view->with(
                'unreadNotificationCount',
                auth()->check()
                    ? \App\Models\Notification::where('user_id', auth()->id())->where('is_read', 0)->count()
                    : 0
            );
        });
    }
}
