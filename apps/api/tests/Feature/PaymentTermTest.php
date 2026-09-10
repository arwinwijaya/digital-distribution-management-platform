<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentTermTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_payment_terms_are_rejected(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'payment-term-invalid-admin@example.test',
            'password' => Hash::make('password'),
        ]);
        $outlet = Outlet::factory()->create(['payment_term_days' => 30]);
        $token = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');
        $headers = ['Authorization' => 'Bearer '.$token];

        foreach ([0, -1, 1.5, 91] as $term) {
            $this->withHeaders($headers)
                ->putJson('/api/admin/outlets/'.$outlet->id.'/payment-terms', ['payment_term_days' => $term])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['payment_term_days']);
        }

        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'payment_term_days' => 30,
        ]);
    }

    public function test_admin_can_set_valid_payment_term(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'payment-term-admin@example.test',
            'password' => Hash::make('password'),
        ]);
        $outlet = Outlet::factory()->create();
        $token = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertOk()->json('data.token');
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->withHeaders($headers)
            ->putJson('/api/admin/outlets/'.$outlet->id.'/payment-terms', ['payment_term_days' => 1])
            ->assertOk()
            ->assertJsonPath('data.payment_term_days', 1);
        $this->withHeaders($headers)
            ->putJson('/api/admin/outlets/'.$outlet->id.'/payment-terms', ['payment_term_days' => 90])
            ->assertOk()
            ->assertJsonPath('data.payment_term_days', 90);
        $this->withHeaders($headers)
            ->getJson('/api/admin/outlets/'.$outlet->id.'/payment-terms')
            ->assertOk()
            ->assertJsonPath('data.payment_term_days', 90);

        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'payment_term_days' => 90,
        ]);
    }
}
