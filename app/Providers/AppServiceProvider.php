<?php

namespace App\Providers;

use App\Auth\SupabaseJwtGuard;
use Illuminate\Foundation\Vite;
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
        // Assets com caminho relativo ao host (/build/...): atrás de proxies/túneis
        // HTTPS sem X-Forwarded-Proto (Cloud Shell Preview, ngrok), URLs absolutas
        // saem em http:// e os navegadores bloqueiam por mixed-content. URLs http*://
        // (modo HMR via hot file) passam intactas.
        $this->app->booted(function (): void {
            $this->app->make(Vite::class)
                ->createAssetPathsUsing(static fn (string $path, $secure = null): string => '/'.ltrim($path, '/'));
        });

        Auth::extend('supabase-jwt', function ($app, $name, array $config) {
            $provider = isset($config['provider'])
                ? $app['auth']->createUserProvider($config['provider'])
                : null;

            return new SupabaseJwtGuard($provider, $app['request'], $config);
        });
    }
}
