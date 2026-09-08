<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppClient;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WhatsAppTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private Product $product;

    private string $secret = 'test-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('whatsapp.enabled', true);
        Config::set('whatsapp.webhook_secret', $this->secret);
        $this->outlet = Outlet::factory()->create(['phone' => '+628123456789']);
        $this->product = Product::factory()->create([
            'name' => 'Coffee',
            'sku' => 'COFFEE-001',
            'price' => 10000,
            'stock_quantity' => 20,
        ]);
    }

    private function webhook(array $payload, ?string $signatureSecret = null)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $secret = $signatureSecret ?? $this->secret;

        return $this->withHeaders([
            'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, $secret),
        ])->postJson('/api/whatsapp/webhook', $payload);
    }

    public function test_valid_message_creates_one_order(): void
    {
        $response = $this->webhook([
            'message_id' => 'wamid-1',
            'from' => '+628123456789',
            'text' => 'ORDER COFFEE-001 2',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'New');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('whatsapp_messages', [
            'provider_message_id' => 'wamid-1',
            'status' => 'processed',
        ]);
    }

    public function test_signature_and_sender_are_required(): void
    {
        $this->postJson('/api/whatsapp/webhook', [
            'message_id' => 'wamid-unsigned',
            'from' => $this->outlet->phone,
            'text' => 'ORDER COFFEE-001 1',
        ])->assertUnauthorized();

        $this->webhook([
            'message_id' => 'wamid-unknown',
            'from' => '+628999999999',
            'text' => 'ORDER COFFEE-001 1',
        ])->assertStatus(422);
    }

    public function test_duplicate_provider_delivery_does_not_create_another_order(): void
    {
        $payload = ['message_id' => 'wamid-duplicate', 'from' => $this->outlet->phone, 'text' => 'ORDER COFFEE-001 2'];
        $this->webhook($payload)->assertCreated();
        $this->webhook($payload)->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_confirmation_notification_and_catalog_use_client(): void
    {
        $client = new class implements WhatsAppClient
        {
            public array $calls = [];

            public function sendText(string $to, string $text): array
            {
                $this->calls[] = ['text', $to, $text];

                return ['id' => 'outbound-1'];
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                $this->calls[] = ['catalog', $to, $catalog];

                return ['id' => 'outbound-2'];
            }
        };
        $this->app->instance(WhatsAppClient::class, $client);

        $order = $this->webhook(['message_id' => 'wamid-notify', 'from' => $this->outlet->phone, 'text' => 'ORDER COFFEE-001 1'])->json('data.id');
        $admin = User::factory()->admin()->create();
        $token = auth()->login($admin);
        $this->withToken($token)->putJson("/api/orders/{$order}/approve")->assertOk();

        $this->assertSame('text', $client->calls[0][0]);
        $this->assertDatabaseHas('whatsapp_messages', ['direction' => 'outbound', 'status' => 'sent']);
    }

    public function test_catalog_is_bounded_and_sent_through_client(): void
    {
        $user = User::factory()->outlet()->create();
        $this->outlet->update(['user_id' => $user->id]);
        $client = new class implements WhatsAppClient
        {
            public array $catalogs = [];

            public function sendText(string $to, string $text): array
            {
                return ['id' => 'text'];
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                $this->catalogs[] = $catalog;

                return ['id' => 'catalog-1'];
            }
        };
        $this->app->instance(WhatsAppClient::class, $client);
        Config::set('whatsapp.catalog_limit', 1);

        $token = auth()->login($user);
        $this->withToken($token)->postJson('/api/whatsapp/catalog')->assertOk()->assertJsonCount(1, 'data.catalog');
        $this->assertCount(1, $client->catalogs[0]);
        $this->assertDatabaseHas('whatsapp_messages', ['message_type' => 'catalog', 'status' => 'sent']);
    }

    public function test_provider_failure_does_not_change_confirmed_order_and_can_retry(): void
    {
        $this->app->instance(WhatsAppClient::class, new class implements WhatsAppClient
        {
            public function sendText(string $to, string $text): array
            {
                throw new \RuntimeException('provider unavailable');
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                throw new \RuntimeException('provider unavailable');
            }
        });
        $orderId = $this->webhook(['message_id' => 'wamid-failure', 'from' => $this->outlet->phone, 'text' => 'ORDER COFFEE-001 1'])->json('data.id');
        $admin = User::factory()->admin()->create();
        $token = auth()->login($admin);
        $this->withToken($token)->putJson("/api/orders/{$orderId}/approve")->assertOk();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'Confirmed']);
        $messageId = WhatsAppMessage::where('direction', 'outbound')->value('id');
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $messageId, 'status' => 'failed']);

        $this->app->instance(WhatsAppClient::class, new class implements WhatsAppClient
        {
            public function sendText(string $to, string $text): array
            {
                return ['id' => 'retry-1'];
            }

            public function sendCatalog(string $to, array $catalog): array
            {
                return ['id' => 'retry-2'];
            }
        });
        $this->withToken($token)->postJson("/api/whatsapp/messages/{$messageId}/retry")->assertOk();
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $messageId, 'status' => 'sent']);
    }

    public function test_ambiguous_input_is_rejected_and_feature_can_be_disabled(): void
    {
        $this->webhook(['message_id' => 'wamid-bad', 'from' => $this->outlet->phone, 'text' => 'ORDER 2'])->assertStatus(422);
        $this->assertDatabaseCount('orders', 0);

        Config::set('whatsapp.enabled', false);
        $this->webhook(['message_id' => 'wamid-disabled', 'from' => $this->outlet->phone, 'text' => 'ORDER COFFEE-001 1'])->assertStatus(202);
        $this->assertDatabaseCount('orders', 0);
    }
}
