<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(\App\Cache\TokenCacheService::class, function () {
            return new \App\Cache\TokenCacheService();
        });

        $this->app->singleton(\App\Services\AuthTokenService::class, function ($app) {
            return new \App\Services\AuthTokenService(
                $app->make(\App\Cache\TokenCacheService::class)
            );
        });

        $this->app->singleton(\App\Services\PasswordResetService::class, function ($app) {
            return new \App\Services\PasswordResetService(
                $app->make(\App\Services\AuthTokenService::class)
            );
        });
    }

}
