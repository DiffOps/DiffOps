<?php

namespace App\Providers;

use App\Auth\SupabaseJwtGuard;
use Illuminate\Support\Facades\Auth;
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
        Auth::extend('supabase-jwt', function ($app, $name, array $config) {
            $provider = isset($config['provider'])
                ? $app['auth']->createUserProvider($config['provider'])
                : null;

            return new SupabaseJwtGuard($provider, $app['request'], $config);
        });
    }
}
