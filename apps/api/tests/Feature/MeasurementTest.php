<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MeasurementTest extends TestCase
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
            'order_id' => 'ORD-MEAS-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => $total,
            'paid_amount' => 0,
            'commission_percentage' => 2,
            'idempotency_key' => 'meas-'.uniqid(),
        ]);
        $order->forceFill(['created_at' => $date, 'updated_at' => $date])->save();

        return $order->fresh();
    }

    // -------------------------------------------------------------- //
    // Cycle 1 — Recent-average fallback for sparse history             //
    // -------------------------------------------------------------- //

    public function test_recent_average_fallback_is_returned_for_sparse_history(): void
    {
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        [, $outlet, $headers] = $this->authenticatedUser();
        $product = Product::factory()->create(['stock_quantity' => 10, 'is_active' => true]);

        // Five orders across 7 distinct days — fewer than 30 but with positive observations.
        for ($day = 1; $day <= 5; $day++) {
            $date = "2026-09-0{$day}";
            $ord = $this->order($outlet, $date, $day * 50);
            OrderItem::create([
                'order_id' => $ord->id,
                'product_id' => $product->id,
                'quantity' => $day,
                'unit_price' => 50,
                'subtotal' => $day * 50,
            ]);
        }

        // Act: recommendation endpoint
        $response = $this->withHeaders($headers)->getJson('/api/ai/recommendations');
        $response->assertOk()->assertJsonPath('status', 'success');
        $recData = $response->json('data');

        // 30-day sparse metadata must be present.
        $this->assertArrayHasKey('days_in_window', $recData['data_sufficiency']);
        $this->assertArrayHasKey('window_days', $recData['data_sufficiency']);
        $this->assertArrayHasKey('recent_average_fallback', $recData['data_sufficiency']);
        $this->assertGreaterThan(0, $recData['data_sufficiency']['days_in_window']);
        $this->assertLessThan(30, $recData['data_sufficiency']['days_in_window']);
        $this->assertSame(30, $recData['data_sufficiency']['window_days']);
        $this->assertTrue($recData['data_sufficiency']['recent_average_fallback']);
        $this->assertNotEmpty($recData['recommendations']);

        // Act: forecast endpoint
        $forecast = $this->withHeaders($headers)->getJson('/api/ai/forecast?period=daily&horizon=2');
        $forecast->assertOk()->assertJsonPath('status', 'success');
        $fcData = $forecast->json('data');

        $this->assertArrayHasKey('days_in_window', $fcData['data_sufficiency']);
        $this->assertArrayHasKey('recent_average_fallback', $fcData['data_sufficiency']);
        $this->assertGreaterThan(0, $fcData['data_sufficiency']['days_in_window']);
        $this->assertTrue($fcData['data_sufficiency']['recent_average_fallback']);

        Carbon::setTestNow();
    }

    // -------------------------------------------------------------- //
    // Cycle 2 — Empty history returns safe output                      //
    // -------------------------------------------------------------- //

    public function test_empty_history_returns_safe_output(): void
    {
        $frozenTime = Carbon::parse('2026-09-14 02:00:00', 'Asia/Jakarta');
        Carbon::setTestNow($frozenTime);

        [, , $headers] = $this->authenticatedUser();

        // Act: recommendation endpoint with zero eligible history
        $response = $this->withHeaders($headers)->getJson('/api/ai/recommendations');
        $response->assertOk()->assertJsonPath('status', 'success');
        $recData = $response->json('data');

        $this->assertSame([], $recData['recommendations']);
        $this->assertTrue($recData['fallback']);
        $this->assertSame('insufficient', $recData['data_sufficiency']['level']);
        $this->assertSame(0, $recData['data_sufficiency']['days_in_window']);
        $this->assertSame(30, $recData['data_sufficiency']['window_days']);
        $this->assertFalse($recData['data_sufficiency']['recent_average_fallback']);
        $this->assertArrayNotHasKey('accuracy', $recData);
        $this->assertArrayNotHasKey('confidence_score', $recData);
        $this->assertFalse($recData['measurement']['measured']);

        // Act: forecast endpoint with zero eligible history
        $forecast = $this->withHeaders($headers)->getJson('/api/ai/forecast?period=daily&horizon=2');
        $forecast->assertOk()->assertJsonPath('status', 'success');
        $fcData = $forecast->json('data');

        $this->assertSame([], $fcData['predictions']);
        $this->assertTrue($fcData['fallback']);
        $this->assertSame('insufficient', $fcData['data_sufficiency']['level']);
        $this->assertSame(0, $fcData['data_sufficiency']['days_in_window']);
        $this->assertFalse($fcData['data_sufficiency']['recent_average_fallback']);
        $this->assertArrayNotHasKey('accuracy', $fcData);
        $this->assertArrayNotHasKey('confidence_score', $fcData);
        $this->assertFalse($fcData['measurement']['measured']);

        Carbon::setTestNow();
    }
}
