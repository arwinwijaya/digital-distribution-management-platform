<?php

namespace App\Providers;

use App\Contracts\WhatsAppClient;
use App\Services\AuthService;
use App\Services\WhatsAppHttpClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService;
        });
        $this->app->bind(WhatsAppClient::class, WhatsAppHttpClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
