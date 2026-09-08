<?php

namespace App\Services;

use App\Models\User;

use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthService
{
    /**
     * Create a new authentication token for the user
     */
    public function createToken(User $user): array
    {
        // For simplicity, we'll use a custom token implementation
        // In production, consider using Laravel Sanctum or JWT
        $token = $this->generateToken($user);
        $expiresIn = config('auth.token_lifetime', 1440); // 24 hours in minutes

        // Store token in cache for validation
        cache()->put(
            "auth_token:{$token}",
            [
                'user_id' => $user->id,
                'created_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addMinutes($expiresIn),
            ],
            $expiresIn * 60
        );

        return [
            'token' => $token,
            'expires_in' => $expiresIn * 60, // Return seconds
        ];
    }

    /**
     * Invalidate a user's token
     */
    public function invalidateToken(User $user): void
    {
        // Find and invalidate all tokens for this user by scanning cache
        // For a production system, you'd track tokens per user in the database
        $token = request()->bearerToken();
        if ($token) {
            cache()->forget("auth_token:{$token}");
        }
    }

    /**
     * Refresh a user's token
     */
    public function refreshToken(User $user): array
    {
        return $this->createToken($user);
    }

    /**
     * Validate a token and return the user
     */
    public function validateToken(string $token): ?User
    {
        $tokenData = cache()->get("auth_token:{$token}");

        if (!$tokenData) {
            return null;
        }

        if (Carbon::now()->isAfter($tokenData['expires_at'])) {
            cache()->forget("auth_token:{$token}");
            return null;
        }

        return User::find($tokenData['user_id']);
    }

    /**
     * Generate a secure token
     */
    protected function generateToken(User $user): string
    {
        $payload = json_encode([
            'user_id' => $user->id,
            'email' => $user->email,
            'iat' => Carbon::now()->timestamp,
            'jti' => Str::uuid(),
        ]);

        return hash_hmac('sha256', $payload, config('app.key'));
    }
}
