<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Read-only order endpoints split out of OrderTest so query/pagination
 * behaviour (admin list + show) stays separate from order creation/approval.
 */
class OrderQueryTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'outlet@ddp.com',
            'password' => Hash::make('password123'),
        ]);

        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'outlet@ddp.com',
            'password' => 'password123',
        ]);

        $this->token = $loginResponse->json('data.token');
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_admin_without_outlet_can_list_and_view_orders(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 10]);
        $orderResponse = $this->withHeaders($this->authHeaders())
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
        $orderId = $orderResponse->json('data.id');

        $admin = User::factory()->admin()->create([
            'email' => 'admin-no-outlet@ddp.com',
            'password' => Hash::make('password123'),
        ]);
        $login = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);
        $headers = ['Authorization' => 'Bearer '.$login->json('data.token')];

        $this->withHeaders($headers)->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $orderId);
        $this->withHeaders($headers)->getJson('/api/admin/orders/'.$orderId)
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);
    }

    // ============================================================
    // Admin orders list: offset cursor + total + default newest-first
    // ============================================================

    /**
     * GWT: Given orders with varied created_at, When GET /admin/orders?limit=100&cursor=0
     * (auth admin), Then data is ordered created_at DESC, meta.total is the count and
     * meta.cursor is the requested offset.
     */
    public function test_admin_orders_list_returns_newest_first_with_total_and_offset_cursor(): void
    {
        $token = $this->loginAsAdmin();
        $oldest = $this->createOrderAt('ORD-LIST-OLDEST', '2026-01-01 08:00:00');
        $newest = $this->createOrderAt('ORD-LIST-NEWEST', '2026-03-01 08:00:00');
        $middle = $this->createOrderAt('ORD-LIST-MIDDLE', '2026-02-01 08:00:00');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/orders?limit=100&cursor=0');

        $response->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.cursor', 0)
            ->assertJsonPath('meta.limit', 100)
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('data.1.id', $middle->id)
            ->assertJsonPath('data.2.id', $oldest->id);
    }

    /**
     * GWT: Given three orders, When GET /admin/orders?limit=1&cursor=1,
     * Then the SECOND page (by OFFSET) is returned and meta.total stays the full count.
     */
    public function test_admin_orders_list_offset_cursor_returns_second_page(): void
    {
        $token = $this->loginAsAdmin();
        $this->createOrderAt('ORD-PAGE-OLDEST', '2026-01-01 08:00:00');
        $newest = $this->createOrderAt('ORD-PAGE-NEWEST', '2026-03-01 08:00:00');
        $middle = $this->createOrderAt('ORD-PAGE-MIDDLE', '2026-02-01 08:00:00');

        $firstPage = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/orders?limit=1&cursor=0');
        $firstPage->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $newest->id)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.has_more', true);

        $secondPage = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/orders?limit=1&cursor=1');
        $secondPage->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.cursor', 1)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.0.id', $middle->id);
    }

    /**
     * GWT: Given an outlet (non-admin) user, When GET /admin/orders,
     * Then the existing admin guard still responds 403 (unchanged).
     */
    public function test_non_admin_cannot_list_orders(): void
    {
        $this->withHeaders($this->authHeaders())
            ->getJson('/api/admin/orders')
            ->assertStatus(403);
    }

    /**
     * GWT: Given orders with different statuses, When GET /admin/orders?sort=status&order=asc,
     * Then data is ordered by status ascending.
     */
    public function test_admin_orders_list_sorts_by_status_ascending(): void
    {
        $token = $this->loginAsAdmin();
        // created_at DESC (default) = New, Confirmed, Cancelled — the opposite of
        // status ASC, so this only passes when the sort param is honoured.
        $this->createOrderAt('ORD-SORT-NEW', '2026-01-03 08:00:00', ['status' => 'New']);
        $this->createOrderAt('ORD-SORT-CONFIRMED', '2026-01-02 08:00:00', ['status' => 'Confirmed']);
        $this->createOrderAt('ORD-SORT-CANCELLED', '2026-01-01 08:00:00', ['status' => 'Cancelled']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/orders?sort=status&order=asc');

        $response->assertOk()
            ->assertJsonPath('data.0.status', 'Cancelled')
            ->assertJsonPath('data.1.status', 'Confirmed')
            ->assertJsonPath('data.2.status', 'New')
            ->assertJsonPath('meta.total', 3);
    }

    /**
     * GWT: Given an invalid sort column, When GET /admin/orders?sort=__proto__&order=desc,
     * Then HTTP 200 with the default newest-first order (never 422/500).
     */
    public function test_admin_orders_list_silently_falls_back_for_invalid_sort(): void
    {
        $token = $this->loginAsAdmin();
        $this->createOrderAt('ORD-FALLBACK-OLDER', '2026-01-01 08:00:00');
        $this->createOrderAt('ORD-FALLBACK-NEWER', '2026-03-01 08:00:00');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/orders?sort=__proto__&order=desc');

        $response->assertOk()
            ->assertJsonPath('data.0.order_id', 'ORD-FALLBACK-NEWER')
            ->assertJsonPath('data.1.order_id', 'ORD-FALLBACK-OLDER')
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * GWT: Given non-scalar (array) sort/order/cursor params, When GET /admin/orders,
     * Then HTTP 200 with the default newest-first order (never a 500).
     */
    public function test_admin_orders_list_handles_non_scalar_params_without_error(): void
    {
        $token = $this->loginAsAdmin();
        $this->createOrderAt('ORD-ARRAY-OLDER', '2026-01-01 08:00:00');
        $this->createOrderAt('ORD-ARRAY-NEWER', '2026-03-01 08:00:00');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/orders?sort[]=x&order[]=y&cursor[]=z');

        $response->assertOk()
            ->assertJsonPath('data.0.order_id', 'ORD-ARRAY-NEWER')
            ->assertJsonPath('data.1.order_id', 'ORD-ARRAY-OLDER')
            ->assertJsonPath('meta.cursor', 0)
            ->assertJsonPath('meta.total', 2);
    }

    private function loginAsAdmin(): string
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin-list@ddp.com',
            'password' => Hash::make('password123'),
        ]);

        return $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->json('data.token');
    }

    /**
     * Persist an order with an explicit created_at/updated_at so list ordering
     * is deterministic without relying on wall-clock time.
     */
    private function createOrderAt(string $orderId, string $createdAt, array $overrides = []): Order
    {
        $order = Order::create(array_merge([
            'order_id' => $orderId,
            'outlet_id' => $this->outlet->id,
            'status' => 'New',
            'total_amount' => 10000,
            'commission_percentage' => 2.00,
            'idempotency_key' => 'key-'.$orderId,
        ], $overrides));

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order;
    }
}
