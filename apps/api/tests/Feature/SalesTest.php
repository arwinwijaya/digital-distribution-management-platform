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

    public function test_sales_visits_are_scoped_and_outlet_users_are_rejected(): void
    {
        $sales = User::factory()->sales()->create();
        $otherSales = User::factory()->sales()->create();
        $outlet = User::factory()->outlet()->create();
        $salesToken = $this->loginAs($sales);
        $otherToken = $this->loginAs($otherSales);
        $outletToken = $this->loginAs($outlet);

        $this->withHeader('Authorization', "Bearer {$salesToken}")
            ->postJson('/api/sales/visits', ['target' => 'Owned target', 'visit_date' => '2026-10-13'])
            ->assertCreated();
        $otherVisit = $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->postJson('/api/sales/visits', ['target' => 'Other target', 'visit_date' => '2026-10-13'])
            ->assertCreated();

        $this->withHeader('Authorization', "Bearer {$salesToken}")
            ->getJson('/api/sales/visits')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.target', 'Owned target');
        $this->withHeader('Authorization', "Bearer {$salesToken}")
            ->getJson('/api/sales/visits/'.$otherVisit->json('data.id'))
            ->assertForbidden();
        $this->withHeader('Authorization', "Bearer {$outletToken}")
            ->postJson('/api/sales/visits', ['target' => 'Forbidden', 'visit_date' => '2026-10-13'])
            ->assertForbidden();
    }

    private function loginAs(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');
    }
}
