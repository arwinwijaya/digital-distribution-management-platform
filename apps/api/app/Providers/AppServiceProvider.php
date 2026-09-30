<?php

namespace App\Providers;

use App\Contracts\WhatsAppClient;
use App\Policies\UserPolicy;
use App\Services\AuthService;
use App\Services\FinanceAuthorizationService;
use App\Services\Recommendation\ModelOutputValidator;
use App\Services\Recommendation\RecommendationModelAdapter;
use App\Services\Recommendation\RecommendationModelAdapterResolver;
use App\Services\RecommendationService;
use App\Services\WhatsAppHttpClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope is a dev-only dependency (require-dev). Register its provider
        // only when the package is actually installed and Telescope is enabled,
        // so production images built with `composer install --no-dev` boot cleanly.
        if (class_exists(\Laravel\Telescope\TelescopeApplicationServiceProvider::class)
            && env('TELESCOPE_ENABLED', true)) {
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }

        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService;
        });
        $this->app->bind(WhatsAppClient::class, WhatsAppHttpClient::class);
        // Phase 9 T17: wire the recommendation adapter seam into the HTTP path.
        // The guarded resolver is the only adapter exposed to callers; the
        // optional external provider is supplied only via the 'recommendation.external'
        // container key (bound by tests/future providers, never by default).
        $this->app->bind(RecommendationModelAdapterResolver::class, function ($app) {
            $external = $app->bound('recommendation.external')
                ? $app->make('recommendation.external')
                : null;

            return new RecommendationModelAdapterResolver(
                $app->make(RecommendationService::class),
                $app->make(ModelOutputValidator::class),
                $external instanceof RecommendationModelAdapter ? $external : null,
            );
        });
        $this->app->bind(RecommendationModelAdapter::class, RecommendationModelAdapterResolver::class);
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
