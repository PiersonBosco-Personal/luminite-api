<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('mcp', function (Request $request) {
            $token = $request->bearerToken();
            return Limit::perMinute(60)->by(
                $token ? hash('sha256', $token) : $request->ip()
            );
        });

        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower($request->input('email')) . '|' . $request->ip();
            return Limit::perMinute(5)->by($key);
        });
    }
}
