<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_user_can_plan_a_visit(): void
    {
        $sales = User::factory()->sales()->create([
            'email' => 'sales@example.com',
            'password' => Hash::make('password123'),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => $sales->email,
            'password' => 'password123',
        ])->json('data.token');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/sales/visits', [
                'target' => 'North Jakarta priority outlets',
                'visit_date' => '2026-10-12',
                'status' => 'planned',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.target', 'North Jakarta priority outlets')
            ->assertJsonPath('data.status', 'planned');
    }
}
