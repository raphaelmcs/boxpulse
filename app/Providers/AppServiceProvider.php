<?php

namespace App\Providers;

use App\Tenancy\CurrentBox;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentBox::class, fn () => new CurrentBox);
    }

    public function boot(): void
    {
        RateLimiter::for('session-state', fn (Request $request) => Limit::perMinute(360)->by($request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('session-commands', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id.'|'.$request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
    }
}
