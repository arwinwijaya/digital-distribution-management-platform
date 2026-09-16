<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppClient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PromotionBroadcastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('whatsapp.enabled', true);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function bearerFor(User $user): string
    {
        $raw = app(\App\Services\AuthService::class)->createToken($user);
        $token = is_array($raw) ? ($raw['token'] ?? array_values($raw)[0]) : $raw;

        return 'Bearer '.$token;
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
            'password' => Hash::make('password123'),
        ]);
    }

    /**
     * Supplier-less active products are always purchasable (legacy compatibility).
     */
    private function makePurchasableProduct(): Product
    {
        return Product::factory()->create([
            'supplier_id' => null,
            'is_active' => true,
        ]);
    }

    /**
     * Deterministic, collision-free phone numbers so canonical_phone stays unique.
     */
    private function makeOutlets(int $count, string $namePrefix = 'Outlet'): array
    {
        $outlets = [];
        for ($i = 0; $i < $count; $i++) {
            $outlets[] = Outlet::factory()->create([
                'name' => $namePrefix.' '.($i + 1),
                'phone' => '+62812'.str_pad((string) ($this->phoneSeed++), 8, '0', STR_PAD_LEFT),
            ]);
        }

        return $outlets;
    }

    private int $phoneSeed = 1;

    private function seedOrder(Outlet $outlet, Product $product, \DateTimeInterface $createdAt): Order
    {
        $order = Order::create([
            'order_id' => 'ORD-BC-'.uniqid(),
            'outlet_id' => $outlet->id,
            'status' => 'New',
            'total_amount' => 10000,
            'idempotency_key' => 'bc-'.uniqid(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10000,
            'subtotal' => 10000,
        ]);

        DB::table('orders')->where('id', $order->id)->update([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $order->fresh();
    }

    private function bindClient(WhatsAppClient $client): void
    {
        $this->app->instance(WhatsAppClient::class, $client);
    }

    private function broadcast(User $admin, Promotion $promo)
    {
        return $this->withHeader('Authorization', $this->bearerFor($admin))
            ->postJson("/api/admin/promotions/{$promo->id}/broadcast");
    }

    // =================================================================
    // Targeting
    // =================================================================

    public function test_broadcast_creates_one_message_per_eligible_outlet(): void
    {
        $admin = $this->makeAdmin();
        $client = new RecordingWhatsAppClient();
        $this->bindClient($client);

        $product = $this->makePurchasableProduct();
        $outlets = $this->makeOutlets(50, 'Warung');
        foreach ($outlets as $outlet) {
            $this->seedOrder($outlet, $product, now()->subDays(10));
        }

        $promo = Promotion::factory()->active()->create();

        $response = $this->broadcast($admin, $promo);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.created', 50)
            ->assertJsonPath('data.already_sent', false)
            ->assertJsonPath('data.sent', 50)
            ->assertJsonPath('data.failed', 0);

        $this->assertSame(50, WhatsAppMessage::promoBroadcast()->count());
        $this->assertSame(50, WhatsAppMessage::promoBroadcast()->where('status', 'sent')->count());
        $this->assertSame(50, count($client->calls));
        $this->assertNotNull($promo->fresh()->broadcast_at);
    }

    public function test_outlet_with_last_order_older_than_30_days_is_excluded(): void
    {
        $admin = $this->makeAdmin();
        $this->bindClient(new RecordingWhatsAppClient());

        $product = $this->makePurchasableProduct();
        $recentOutlets = $this->makeOutlets(2, 'Recent');
        foreach ($recentOutlets as $outlet) {
            $this->seedOrder($outlet, $product, now()->subDays(10));
        }

        $staleOutlet = $this->makeOutlets(1, 'Stale')[0];
        $this->seedOrder($staleOutlet, $product, now()->subDays(40));

        $promo = Promotion::factory()->active()->create();

        $this->broadcast($admin, $promo)
            ->assertOk()
            ->assertJsonPath('data.created', 2);

        $this->assertSame(2, WhatsAppMessage::promoBroadcast()->count());
        $this->assertDatabaseMissing('whatsapp_messages', [
            'outlet_id' => $staleOutlet->id,
            'message_type' => 'promo_broadcast',
        ]);
    }

    public function test_outlet_whose_recent_order_referenced_inactive_supplier_product_is_excluded(): void
    {
        $admin = $this->makeAdmin();
        $this->bindClient(new RecordingWhatsAppClient());

        $inactiveSupplier = Supplier::factory()->create(['subscription_status' => 'inactive']);
        $unpurchasable = Product::factory()->create([
            'supplier_id' => $inactiveSupplier->id,
            'is_active' => true,
        ]);
        $purchasable = $this->makePurchasableProduct();

        [$goodOutlet, $badOutlet] = $this->makeOutlets(2, 'Target');
        $this->seedOrder($goodOutlet, $purchasable, now()->subDays(5));
        $this->seedOrder($badOutlet, $unpurchasable, now()->subDays(5));

        $promo = Promotion::factory()->active()->create();

        $this->broadcast($admin, $promo)
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('whatsapp_messages', [
            'outlet_id' => $goodOutlet->id,
            'message_type' => 'promo_broadcast',
        ]);
        $this->assertDatabaseMissing('whatsapp_messages', [
            'outlet_id' => $badOutlet->id,
            'message_type' => 'promo_broadcast',
        ]);
    }

    public function test_zero_eligible_outlets_returns_zero_count(): void
    {
        $admin = $this->makeAdmin();
        $this->bindClient(new RecordingWhatsAppClient());

        $promo = Promotion::factory()->active()->create();

        $this->broadcast($admin, $promo)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.sent', 0)
            ->assertJsonPath('data.failed', 0)
            ->assertJsonPath('data.already_sent', false);

        $this->assertSame(0, WhatsAppMessage::promoBroadcast()->count());
    }

    // =================================================================
    // Idempotency
    // =================================================================

    public function test_second_broadcast_reports_already_sent_without_duplicates(): void
    {
        $admin = $this->makeAdmin();
        $client = new RecordingWhatsAppClient();
        $this->bindClient($client);

        $product = $this->makePurchasableProduct();
        foreach ($this->makeOutlets(3, 'Idem') as $outlet) {
            $this->seedOrder($outlet, $product, now()->subDays(3));
        }

        $promo = Promotion::factory()->active()->create();

        $this->broadcast($admin, $promo)
            ->assertOk()
            ->assertJsonPath('data.created', 3)
            ->assertJsonPath('data.sent', 3);

        $firstCallCount = count($client->calls);
        $this->assertSame(3, $firstCallCount);

        $second = $this->broadcast($admin, $promo);

        $second->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('already_sent', true)
            ->assertJsonPath('data.already_sent', true)
            ->assertJsonPath('data.created', 0);

        // No new rows and no additional provider calls on the second POST.
        $this->assertSame(3, WhatsAppMessage::promoBroadcast()->count());
        $this->assertSame($firstCallCount, count($client->calls));
    }

    public function test_retry_resends_only_failed_messages(): void
    {
        $admin = $this->makeAdmin();

        $product = $this->makePurchasableProduct();
        [$failing, $healthyA, $healthyB] = $this->makeOutlets(3, 'Retry');
        foreach ([$failing, $healthyA, $healthyB] as $outlet) {
            $this->seedOrder($outlet, $product, now()->subDays(3));
        }

        $promo = Promotion::factory()->active()->create();

        // A single provider double is bound for the whole test: the route's
        // controller instance is cached, so rebinding mid-test would not be
        // observed. Recovery is modelled by mutating the double instead.
        $provider = new RecordingWhatsAppClient([$failing->phone]);
        $this->bindClient($provider);

        // First attempt: the provider rejects only one outlet.
        $this->broadcast($admin, $promo)
            ->assertOk()
            ->assertJsonPath('data.created', 3)
            ->assertJsonPath('data.sent', 2)
            ->assertJsonPath('data.failed', 1);

        $failedMessage = WhatsAppMessage::promoBroadcast()
            ->where('outlet_id', $failing->id)
            ->firstOrFail();
        $this->assertSame('failed', $failedMessage->status);

        // Second attempt: provider recovers. Only the failed row must be re-sent.
        $provider->failingPhones = [];
        $recovered = $provider;
        $callsBeforeRecovery = count($provider->calls);

        $this->broadcast($admin, $promo)
            ->assertOk()
            ->assertJsonPath('already_sent', true)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.sent', 1)
            ->assertJsonPath('data.failed', 0);

        $this->assertCount($callsBeforeRecovery + 1, $recovered->calls);
        $retryCall = $recovered->calls[$callsBeforeRecovery];
        $this->assertSame($failing->phone, $retryCall['to']);
        // The provider identity is stable, so the retry cannot double-send.
        $this->assertSame(
            hash('sha256', "promo-broadcast:{$promo->id}:{$failing->id}"),
            $retryCall['key']
        );

        $this->assertSame(3, WhatsAppMessage::promoBroadcast()->count());
        $this->assertSame(3, WhatsAppMessage::promoBroadcast()->where('status', 'sent')->count());
        $this->assertSame('sent', $failedMessage->fresh()->status);
    }

    // =================================================================
    // Message content
    // =================================================================

    public function test_message_body_contains_promotion_details_and_outlet_name(): void
    {
        $admin = $this->makeAdmin();
        $client = new RecordingWhatsAppClient();
        $this->bindClient($client);

        $product = $this->makePurchasableProduct();
        $outlet = Outlet::factory()->create([
            'name' => 'Warung Sinar Jaya',
            'phone' => '+6281299990001',
        ]);
        $this->seedOrder($outlet, $product, now()->subDays(2));

        $start = now()->subWeek()->format('Y-m-d');
        $end = now()->addWeek()->format('Y-m-d');

        $promo = Promotion::factory()->create([
            'name' => 'Diskon Akhir Bulan',
            'discount_type' => 'percentage',
            'discount_value' => 25,
            'min_order' => 50000,
            'is_active' => true,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        $this->broadcast($admin, $promo)->assertOk();

        $message = WhatsAppMessage::promoBroadcast()->firstOrFail();

        $this->assertSame('promo_broadcast', $message->message_type);
        $this->assertSame($outlet->id, $message->outlet_id);
        $this->assertSame($outlet->phone, $message->phone);

        $body = (string) $message->body;
        $this->assertStringContainsString('Diskon Akhir Bulan', $body);
        $this->assertStringContainsString('25.00', $body);
        $this->assertStringContainsString('percentage', $body);
        $this->assertStringContainsString($start, $body);
        $this->assertStringContainsString($end, $body);
        $this->assertStringContainsString('50000.00', $body);
        $this->assertStringContainsString('Warung Sinar Jaya', $body);

        // The provider receives the same persisted body.
        $this->assertSame($body, $client->calls[0]['text']);
    }

    // =================================================================
    // Authorization
    // =================================================================

    public function test_non_admin_cannot_broadcast(): void
    {
        $this->bindClient(new RecordingWhatsAppClient());

        $outletUser = User::factory()->outlet()->create([
            'is_active' => true,
            'password' => Hash::make('password123'),
        ]);
        $promo = Promotion::factory()->active()->create();

        $this->withHeader('Authorization', $this->bearerFor($outletUser))
            ->postJson("/api/admin/promotions/{$promo->id}/broadcast")
            ->assertStatus(403);

        $this->assertSame(0, WhatsAppMessage::promoBroadcast()->count());
        $this->assertNull($promo->fresh()->broadcast_at);
    }
}

/**
 * Records provider calls and can reject specific destination phones, which
 * lets the retry scenario fail deterministically without touching the network.
 */
class RecordingWhatsAppClient implements WhatsAppClient
{
    /** @var array<int, array{to: string, text: string, key: string}> */
    public array $calls = [];

    /**
     * Destinations the provider currently rejects. Mutable so a single bound
     * double can model an outage followed by recovery within one test, which
     * container rebinding cannot do (the route caches its controller).
     *
     * @var list<string>
     */
    public array $failingPhones;

    /** @param list<string> $failingPhones */
    public function __construct(array $failingPhones = [])
    {
        $this->failingPhones = $failingPhones;
    }

    public function sendText(string $to, string $text): array
    {
        return $this->sendTextWithIdempotency($to, $text, 'unkeyed');
    }

    public function sendTextWithIdempotency(string $to, string $text, string $idempotencyKey): array
    {
        if (in_array($to, $this->failingPhones, true)) {
            throw new \RuntimeException('provider unavailable');
        }

        $this->calls[] = ['to' => $to, 'text' => $text, 'key' => $idempotencyKey];

        // provider_message_id is globally unique, so the fake must produce a
        // distinct id per successful send rather than per client instance.
        return ['id' => 'promo-'.self::$providerSequence++];
    }

    private static int $providerSequence = 0;

    public function sendCatalog(string $to, array $catalog): array
    {
        return ['id' => 'catalog'];
    }
}
