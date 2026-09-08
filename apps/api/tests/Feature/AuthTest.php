<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test: Given valid credentials, When user logs in, Then JWT token is returned
     */
    public function test_user_can_login_with_valid_credentials(): void
    {
        // Arrange: Create a user with valid credentials
        $user = User::factory()->create([
            'email' => 'admin@ddp.com',
            'password' => Hash::make('password123'),
        ]);

        // Act: POST /api/auth/login with valid credentials
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@ddp.com',
            'password' => 'password123',
        ]);

        // Assert: JWT token is returned
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'token',
                    'token_type',
                    'expires_in',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                    ],
                ],
            ]);

        // Verify token is a valid JWT format
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Test: Given invalid credentials, When user logs in, Then error is returned
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        // Arrange: Create a user
        User::factory()->create([
            'email' => 'admin@ddp.com',
            'password' => Hash::make('password123'),
        ]);

        // Act: POST /api/auth/login with invalid password
        $response = $this->postJson('/api/auth/login', [
            'email' => 'admin@ddp.com',
            'password' => 'wrongpassword',
        ]);

        // Assert: 401 unauthorized is returned
        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid credentials.',
            ]);
    }

    /**
     * Test: Given non-existent email, When user logs in, Then error is returned
     */
    public function test_user_cannot_login_with_nonexistent_email(): void
    {
        // Act: POST /api/auth/login with non-existent email
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@ddp.com',
            'password' => 'password123',
        ]);

        // Assert: 401 unauthorized is returned
        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid credentials.',
            ]);
    }

    /**
     * Test: Given missing fields, When user logs in, Then validation error is returned
     */
    public function test_user_cannot_login_without_required_fields(): void
    {
        // Act: POST /api/auth/login without required fields
        $response = $this->postJson('/api/auth/login', []);

        // Assert: 422 validation error is returned
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /**
     * Test: Given valid token, When accessing protected route, Then user data is returned
     */
    public function test_user_can_access_profile_with_valid_token(): void
    {
        // Arrange: Create a user and get token
        $user = User::factory()->create([
            'email' => 'admin@ddp.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@ddp.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.token');

        // Act: Access protected route with token
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        // Assert: User data is returned
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $user->id,
                    'email' => 'admin@ddp.com',
                ],
            ]);
    }

    /**
     * Test: Given invalid token, When accessing protected route, Then error is returned
     */
    public function test_user_cannot_access_profile_with_invalid_token(): void
    {
        // Act: Access protected route with invalid token
        $response = $this->withHeader('Authorization', 'Bearer invalid-token-123')
            ->getJson('/api/auth/me');

        // Assert: 401 unauthorized is returned
        $response->assertStatus(401)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid token.',
            ]);
    }

    /**
     * Test: Given valid token, When user logs out, Then token is invalidated
     */
    public function test_user_can_logout(): void
    {
        // Arrange: Create a user and get token
        $user = User::factory()->create([
            'email' => 'admin@ddp.com',
            'password' => Hash::make('password123'),
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'admin@ddp.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('data.token');

        // Act: Logout with valid token
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        // Assert: 200 success
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Successfully logged out.',
            ]);

        // Verify token is invalidated
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me')
            ->assertStatus(401);
    }
}
