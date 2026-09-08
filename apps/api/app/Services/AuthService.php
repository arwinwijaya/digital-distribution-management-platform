<?php

namespace App\Services;

use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthService
{
    /**
     * Create a new JWT token for the user
     */
    public function createToken(User $user): array
    {
        $token = JWTAuth::fromUser($user);
        $expiresIn = config('jwt.ttl', 1440) * 60; // Convert minutes to seconds

        return [
            'token' => $token,
            'expires_in' => $expiresIn,
        ];
    }

    /**
     * Invalidate (blacklist) a specific JWT token
     */
    public function invalidateToken(User $user, string $token): void
    {
        JWTAuth::setToken($token)->invalidate();
    }

    /**
     * Refresh a user's token
     */
    public function refreshToken(User $user): array
    {
        $token = JWTAuth::fromUser($user);
        $expiresIn = config('jwt.ttl', 1440) * 60;

        return [
            'token' => $token,
            'expires_in' => $expiresIn,
        ];
    }

    /**
     * Validate a JWT token and return the user
     */
    public function validateToken(string $token): ?User
    {
        try {
            $user = JWTAuth::setToken($token)->authenticate();
            return $user ?: null;
        } catch (JWTException $e) {
            return null;
        }
    }
}
