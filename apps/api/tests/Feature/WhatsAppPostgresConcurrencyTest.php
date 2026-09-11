<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\WhatsAppMessage;
use App\Services\OrderCreationService;
use Tests\Feature\Support\PostgresConcurrencyFeatureCase;

/**
 * Requires a dedicated migrated PostgreSQL test database.
 *
 * Run with DB_CONNECTION=pgsql and the database credentials from the test
 * environment. It launches two real Laravel HTTP workers, not nested test
 * clients, so each request has an independent PostgreSQL connection.
 */
class WhatsAppPostgresConcurrencyTest extends PostgresConcurrencyFeatureCase
{
    public function test_concurrent_public_webhooks_converge_on_one_inbound_message_and_order(): void
    {
        $providerId = $this->prefix.'-inbound';
        $payload = [
            'message_id' => $providerId,
            'from' => $this->outlet->phone,
            'text' => 'ORDER '.$this->product->sku.' 1',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->prefix);

        $this->startServers('whatsapp-inbound');
        $responses = $this->runConcurrent([
            ['headers' => ['X-Hub-Signature-256' => $signature], 'body' => $body],
            ['headers' => ['X-Hub-Signature-256' => $signature], 'body' => $body],
        ], '/api/whatsapp/webhook');

        foreach ($responses as $response) {
            $this->assertContains($response['status'], [200, 201]);
            $this->assertNotSame(500, $response['status']);
        }
        $this->assertSame(1, WhatsAppMessage::where('provider_message_id', $providerId)->count());
        $this->assertSame(1, Order::where('idempotency_key', hash('sha256', 'whatsapp:'.$providerId))->count());
        $this->orderIdentity = hash('sha256', 'whatsapp:'.$providerId);
    }

    public function test_concurrent_notifications_converge_on_one_logical_message(): void
    {
        $this->orderIdentity = hash('sha256', $this->prefix.'-order');
        $order = $this->app->make(OrderCreationService::class)->create(
            ['items' => [['product_id' => $this->product->id, 'quantity' => 1]]],
            $this->outlet,
            $this->orderIdentity,
        )['order'];
        $order->update(['status' => 'Confirmed']);
        $token = auth()->login($this->admin);

        $this->startServers('whatsapp-outbound');
        $responses = $this->runConcurrent([
            ['headers' => ['Authorization' => 'Bearer '.$token], 'body' => '{}'],
            ['headers' => ['Authorization' => 'Bearer '.$token], 'body' => '{}'],
        ], '/api/whatsapp/orders/'.$order->id.'/notification');

        foreach ($responses as $response) {
            $this->assertContains($response['status'], [200, 503]);
            $this->assertNotSame(500, $response['status']);
        }
        $message = WhatsAppMessage::where('logical_key', 'order-confirmation:'.$order->id)->first();
        $this->assertNotNull($message);
        $this->assertSame(hash('sha256', 'order-confirmation:'.$order->id), $message->provider_idempotency_key);
        $this->assertSame(1, WhatsAppMessage::where('logical_key', 'order-confirmation:'.$order->id)->count());
    }
}
