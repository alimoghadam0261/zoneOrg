<?php

namespace App\Providers;

use App\Services\Geofencing\AccessRuleResolver;
use App\Services\Geofencing\ZoneEvaluator;
use App\Services\Geofencing\ZoneIndex;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The zone index is per-request (in-process) but caches across pings
        // of long-lived workers / queued jobs.
        $this->app->singleton(ZoneIndex::class);
        $this->app->singleton(AccessRuleResolver::class);
        $this->app->singleton(ZoneEvaluator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('telemetry', function (Request $request) {
            $token = $request->bearerToken();

            $key = $token
                ? 'token:'.substr(hash('sha256', $token), 0, 16)
                : 'ip:'.$request->ip();

            $perSecond = max(1, (int) config('zone.ping_max_per_second'));

            return Limit::perSecond($perSecond)->by('telemetry:'.$key);
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by('login:'.$request->ip());
        });

        RateLimiter::for('dashboard', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
