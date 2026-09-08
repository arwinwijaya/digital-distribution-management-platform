<?php

namespace App\Providers;

use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Auth::viaRequest('bearer-token', function ($request) {
            $token = $request->bearerToken();

            if (!$token) {
                return null;
            }

            return app(AuthService::class)->validateToken($token);
        });
    }
}
