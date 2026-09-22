<?php

namespace Tests\Feature\FieldOps;

use App\Models\Outlet;
use App\Models\SalesVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 8 — T6: sales visit check-in / check-out with GPS + radius guard.
 *
 * AC-2 contract: owner-scoped check-in within the outlet radius, 422 outside the
 * radius / when the outlet has no coordinates, 403 for another sales rep, 409 on
 * a duplicate check-in, and check-out completing the visit.
 */
class SalesVisitCheckinTest extends TestCase
{
    use RefreshDatabase;

    private function sales(string $email = 'sales-visit@example.com'): User
    {
        return User::factory()->sales()->create([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
    }

    private function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');
    }

    private function visit(User $sales, array $outletAttributes = []): SalesVisit
    {
        $outlet = Outlet::factory()->create(array_merge([
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ], $outletAttributes));

        return SalesVisit::create([
            'sales_user_id' => $sales->id,
            'outlet_id' => $outlet->id,
            'visit_date' => now()->toDateString(),
            'status' => 'planned',
        ]);
    }

    public function test_sales_can_check_in_within_radius(): void
    {
        $sales = $this->sales();
        $visit = $this->visit($sales);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->login($sales))
            ->postJson("/api/sales/visits/{$visit->id}/check-in", [
                'latitude' => -6.2000500,
                'longitude' => 106.8167000,
                'accuracy_m' => 12,
            ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.check_in_latitude', '-6.2000500')
            ->assertJsonPath('data.status', 'planned');

        $fresh = $visit->fresh();
        $this->assertNotNull($fresh->check_in_at);
        $this->assertNotNull($fresh->check_in_latitude);
        $this->assertSame(12, $fresh->check_in_accuracy_m);
    }

    public function test_check_in_outside_radius_is_rejected(): void
    {
        $sales = $this->sales();
        $visit = $this->visit($sales);

        // ~1.1 km north of the outlet.
        $this->withHeader('Authorization', 'Bearer '.$this->login($sales))
            ->postJson("/api/sales/visits/{$visit->id}/check-in", [
                'latitude' => -6.1900000,
                'longitude' => 106.8166667,
            ])
            ->assertStatus(422);

        $this->assertNull($visit->fresh()->check_in_at);
    }

    public function test_check_in_is_rejected_when_outlet_has_no_coordinates(): void
    {
        $sales = $this->sales();
        $visit = $this->visit($sales, ['latitude' => null, 'longitude' => null]);

        $this->withHeader('Authorization', 'Bearer '.$this->login($sales))
            ->postJson("/api/sales/visits/{$visit->id}/check-in", [
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
            ])
            ->assertStatus(422);
    }

    public function test_another_sales_rep_cannot_check_in(): void
    {
        $owner = $this->sales('owner-visit@example.com');
        $other = $this->sales('other-visit@example.com');
        $visit = $this->visit($owner);

        $this->withHeader('Authorization', 'Bearer '.$this->login($other))
            ->postJson("/api/sales/visits/{$visit->id}/check-in", [
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
            ])
            ->assertStatus(403);
    }

    public function test_duplicate_check_in_is_rejected(): void
    {
        $sales = $this->sales();
        $visit = $this->visit($sales);
        $token = 'Bearer '.$this->login($sales);

        $this->withHeader('Authorization', $token)
            ->postJson("/api/sales/visits/{$visit->id}/check-in", [
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
            ])
            ->assertOk();

        $this->withHeader('Authorization', $token)
            ->postJson("/api/sales/visits/{$visit->id}/check-in", [
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
            ])
            ->assertStatus(409);
    }

    public function test_check_out_completes_the_visit(): void
    {
        $sales = $this->sales();
        $visit = $this->visit($sales);
        $token = 'Bearer '.$this->login($sales);

        $this->withHeader('Authorization', $token)
            ->postJson("/api/sales/visits/{$visit->id}/check-in", [
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
            ])
            ->assertOk();

        $this->withHeader('Authorization', $token)
            ->postJson("/api/sales/visits/{$visit->id}/check-out", [
                'latitude' => -6.2000100,
                'longitude' => 106.8166800,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $fresh = $visit->fresh();
        $this->assertNotNull($fresh->check_out_at);
        $this->assertSame('completed', $fresh->status);
    }

    public function test_check_out_without_check_in_is_rejected(): void
    {
        $sales = $this->sales();
        $visit = $this->visit($sales);

        $this->withHeader('Authorization', 'Bearer '.$this->login($sales))
            ->postJson("/api/sales/visits/{$visit->id}/check-out", [
                'latitude' => -6.2000000,
                'longitude' => 106.8166667,
            ])
            ->assertStatus(422);
    }

    public function test_check_in_requires_authentication(): void
    {
        $visit = $this->visit($this->sales());

        $this->postJson("/api/sales/visits/{$visit->id}/check-in", [
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
        ])->assertStatus(401);
    }
}
