<?php

namespace App\Providers;

use App\Services\SettingsRepository;
use App\View\Composers\SidebarComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer(['partials.sidebar', 'partials.topbar'], SidebarComposer::class);

        // Admin overrides of the scoring configuration.
        $this->app->make(SettingsRepository::class)->apply();

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(90)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
