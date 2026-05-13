<?php

namespace App\Providers;

use App\Models\Rating;
use App\Observers\RatingObserver;
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
        $this->configureRateLimiters();
        Rating::observe(RatingObserver::class);
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Spec 10.: order creation 10/hour per user, price increase 30/hour per order,
        // partner claim 60/minute per partner. Defined here so routes can use named limiters.
        RateLimiter::for('order-create', fn (Request $request) => Limit::perHour(10)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('order-price-increase', fn (Request $request) => Limit::perHour(30)
            ->by($request->route('id') ?? $request->ip()));

        RateLimiter::for('partner-claim', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
