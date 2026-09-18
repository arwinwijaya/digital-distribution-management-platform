<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AnalyticsInsightEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tokenFor(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->json('data.token');
    }

    private function headers(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    /**
     * RED CYCLE 1: route + auth gate.
     */
    public function test_active_admin_receives_the_fixed_window_insight_payload(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'insight-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->withHeaders($this->headers($this->tokenFor($admin)))
            ->getJson('/api/analytics/insight');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.comparison.period.end_date', now()->toDateString())
            ->assertJsonPath('data.comparison.period.start_date', now()->copy()->subDays(29)->toDateString());
    }

    public function test_active_platform_owner_is_allowed(): void
    {
        $owner = User::factory()->create([
            'role' => 'platform_owner',
            'is_active' => true,
            'email' => 'insight-owner@ddp.test',
            'password' => Hash::make('password123'),
        ]);

        $this->withHeaders($this->headers($this->tokenFor($owner)))
            ->getJson('/api/analytics/insight')
            ->assertOk()
            ->assertJsonPath('status', 'success');
    }

    public function test_outlet_user_is_forbidden(): void
    {
        $outlet = User::factory()->outlet()->create([
            'email' => 'insight-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);

        $this->withHeaders($this->headers($this->tokenFor($outlet)))
            ->getJson('/api/analytics/insight')
            ->assertForbidden()
            ->assertJsonPath('status', 'error');
    }

    public function test_request_without_token_is_unauthorized(): void
    {
        $this->getJson('/api/analytics/insight')->assertUnauthorized();
    }

    public function test_inactive_platform_owner_is_forbidden(): void
    {
        $owner = User::factory()->create([
            'role' => 'platform_owner',
            'is_active' => false,
            'email' => 'insight-inactive-owner@ddp.test',
            'password' => Hash::make('password123'),
        ]);

        $this->withHeaders($this->headers($this->tokenFor($owner)))
            ->getJson('/api/analytics/insight')
            ->assertForbidden()
            ->assertJsonPath('status', 'error');
    }

    /**
     * Characterization/regression test (NOT a RED cycle): the insight endpoint
     * ignores date params and the Dashboard contract is unchanged. Expected to
     * pass immediately once the route exists.
     */
    public function test_date_params_are_ignored_and_dashboard_contract_is_unchanged(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'insight-params@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $headers = $this->headers($this->tokenFor($admin));

        $this->withHeaders($headers)
            ->getJson('/api/analytics/insight?start_date=2020-01-01&end_date=2020-01-31')
            ->assertOk()
            ->assertJsonPath('data.comparison.period.end_date', now()->toDateString());

        $this->withHeaders($headers)
            ->getJson('/api/analytics/dashboard')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => ['metrics', 'sales_trends', 'outlet_performance'],
            ])
            ->assertJsonMissingPath('data.metrics_delta');
    }
}
