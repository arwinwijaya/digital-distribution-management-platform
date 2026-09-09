<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AITest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(string $role = 'outlet'): array
    {
        $user = User::factory()->state(['role' => $role])->create([
            'password' => Hash::make('password123'),
        ]);
        $outlet = $role === 'outlet' ? Outlet::factory()->create(['user_id' => $user->id]) : null;
        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->json('data.token');

        return [$user, $outlet, ['Authorization' => "Bearer {$token}"]];
    }

    private function order(Outlet $outlet, string $date, float $total = 100): Order
    {
        $order = Order::create([
            'order_id' => 'ORD-AI-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => $total,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'ai-'.uniqid(),
        ]);
        $order->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

        return $order->fresh();
    }

    public function test_outlet_purchase_history_returns_bounded_ranked_recommendations(): void
    {
        [, $outlet, $headers] = $this->authenticatedUser();
        $popular = Product::factory()->create(['name' => 'Popular', 'stock_quantity' => 10, 'is_active' => true]);
        $inactive = Product::factory()->create(['name' => 'Inactive', 'stock_quantity' => 10, 'is_active' => false]);
        $unavailable = Product::factory()->create(['name' => 'Unavailable', 'stock_quantity' => 0, 'is_active' => true]);
        $order = $this->order($outlet, '2025-09-01', 200);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $popular->id, 'quantity' => 4, 'unit_price' => 50, 'subtotal' => 200]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $inactive->id, 'quantity' => 8, 'unit_price' => 10, 'subtotal' => 80]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $unavailable->id, 'quantity' => 9, 'unit_price' => 10, 'subtotal' => 90]);

        $response = $this->withHeaders($headers)->getJson('/api/ai/recommendations');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.fallback', false)
            ->assertJsonStructure(['data' => ['recommendations', 'limit', 'method', 'data_points']]);
        $this->assertSame([$popular->id], collect($response->json('data.recommendations'))->pluck('product_id')->all());
        $this->assertLessThanOrEqual(10, count($response->json('data.recommendations')));
    }

    public function test_forecast_validates_period_and_horizon_and_is_deterministic(): void
    {
        [, $outlet, $headers] = $this->authenticatedUser();
        for ($day = 1; $day <= 4; $day++) {
            $this->order($outlet, "2025-09-0{$day}", $day * 100);
        }

        $first = $this->withHeaders($headers)->getJson('/api/ai/forecast?period=daily&horizon=2');
        $second = $this->withHeaders($headers)->getJson('/api/ai/forecast?period=daily&horizon=2');
        $first->assertOk()->assertJsonPath('data.period', 'daily')->assertJsonPath('data.horizon', 2);
        $this->assertSame($first->json('data.predictions'), $second->json('data.predictions'));
        $this->withHeaders($headers)->getJson('/api/ai/forecast?period=yearly&horizon=2')->assertStatus(422);
        $this->withHeaders($headers)->getJson('/api/ai/forecast?period=monthly&horizon=0')->assertStatus(422);
    }

    public function test_sparse_history_returns_safe_fallback_and_explainable_segmentation(): void
    {
        [, , $headers] = $this->authenticatedUser();

        $recommendations = $this->withHeaders($headers)->getJson('/api/ai/recommendations');
        $recommendations->assertOk()->assertJsonPath('data.recommendations', [])->assertJsonPath('data.fallback', true);

        $forecast = $this->withHeaders($headers)->getJson('/api/ai/forecast?period=weekly&horizon=2');
        $forecast->assertOk()->assertJsonPath('data.predictions', [])->assertJsonPath('data.confidence', 'low')->assertJsonPath('data.data_sufficiency.sufficient', false);

        $segmentation = $this->withHeaders($headers)->getJson('/api/ai/segmentation');
        $segmentation->assertOk()->assertJsonPath('data.segment', 'new')->assertJsonPath('data.confidence', 'low')->assertJsonStructure(['data' => ['signals', 'method', 'measurement']]);
    }

    public function test_outlet_cannot_request_another_outlets_private_ai_signals_but_admin_can(): void
    {
        [, $firstOutlet, $firstHeaders] = $this->authenticatedUser();
        [, $secondOutlet] = $this->authenticatedUser();
        $product = Product::factory()->create();
        $order = $this->order($secondOutlet, '2025-09-01');
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 50, 'subtotal' => 100]);

        $this->withHeaders($firstHeaders)->getJson('/api/ai/recommendations?outlet_id='.$secondOutlet->id)->assertForbidden();
        $this->withHeaders($firstHeaders)->getJson('/api/ai/forecast?outlet_id='.$secondOutlet->id.'&period=daily&horizon=1')->assertForbidden();
        $this->withHeaders($firstHeaders)->getJson('/api/ai/segmentation?outlet_id='.$secondOutlet->id)->assertForbidden();

        [, , $adminHeaders] = $this->authenticatedUser('admin');
        $this->withHeaders($adminHeaders)->getJson('/api/ai/recommendations?outlet_id='.$secondOutlet->id)->assertOk();
    }
}
