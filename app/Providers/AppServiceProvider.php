<?php

namespace App\Providers;

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
        \Illuminate\Support\Facades\View::composer(['layout', 'staff', 'support.links'], function ($view) {
            $user = auth()->user();
            $view->with('navBadges', $user ? \App\Services\NavigationBadges::for($user) : []);
            $view->with('unreadInformation', $user && $user->role === 'reseller'
                ? \App\Models\Announcement::live()->unreadFor($user->id)->latest('starts_at')->limit(10)->get() : collect());
        });
        \Illuminate\Pagination\Paginator::defaultView('pagination.biuble');
        \Illuminate\Support\Facades\RateLimiter::for('store-web', fn ($request) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(120)->by($request->user()?->id ?? $request->ip()));
        foreach (['money-actions'=>10,'otp-send'=>8,'otp-verify'=>12,'security-send'=>5,'security-verify'=>10] as $name=>$attempts) {
            \Illuminate\Support\Facades\RateLimiter::for($name, fn ($request) =>
                \Illuminate\Cache\RateLimiting\Limit::perMinutes(10,$attempts)->by($request->user()?->id ?? $request->ip()));
        }
    }
}
