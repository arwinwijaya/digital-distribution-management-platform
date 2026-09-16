<?php

namespace App\Providers;

use App\Contracts\WhatsAppClient;
use App\Policies\UserPolicy;
use App\Services\AuthService;
use App\Services\FinanceAuthorizationService;
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
        $this->app->bind(UserPolicy::class, function ($app) {
            return new UserPolicy(app(FinanceAuthorizationService::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
