<?php

namespace Tests\Feature;

use App\Models\SalesVisit;
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

    public function test_sales_visits_pagination_is_bounded_and_scoped(): void
    {
        $sales = User::factory()->sales()->create();
        $otherSales = User::factory()->sales()->create();
        $salesToken = $this->loginAs($sales);

        for ($i = 1; $i <= 12; $i++) {
            SalesVisit::create([
                'sales_user_id' => $sales->id,
                'visit_date' => sprintf('2026-10-%02d', $i),
                'target' => "Owned target {$i}",
            ]);
        }
        SalesVisit::create([
            'sales_user_id' => $otherSales->id,
            'visit_date' => '2026-10-12',
            'target' => 'Other target',
        ]);

        // Page 1: the newest 10 of the 12 scoped visits, newest first.
        $page1 = $this->withHeader('Authorization', "Bearer {$salesToken}")
            ->getJson('/api/sales/visits?page=1&limit=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('data.0.visit_date', '2026-10-12');

        $owners = collect($page1->json('data'))->pluck('sales_user_id')->unique()->values()->all();
        $this->assertSame([$sales->id], $owners);

        // Page 2: the remaining 2; total stays 12 (counted before the offset).
        $this->withHeader('Authorization', "Bearer {$salesToken}")
            ->getJson('/api/sales/visits?page=2&limit=10')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.page', 2)
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.has_more', false);

        // Default page size is 10.
        $this->withHeader('Authorization', "Bearer {$salesToken}")
            ->getJson('/api/sales/visits')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.total', 12);

        // Invalid pagination params are rejected.
        foreach (['page=0', 'limit=0', 'limit=101'] as $query) {
            $this->withHeader('Authorization', "Bearer {$salesToken}")
                ->getJson("/api/sales/visits?{$query}")
                ->assertStatus(422);
        }
    }

    private function loginAs(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');
    }
}
