<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Public status endpoint: 60 requests per minute per IP (constitution VII).
        RateLimiter::for('public-health', static fn (Request $request): Limit => Limit::perMinute(60)->by((string) $request->ip()));
    }
}
