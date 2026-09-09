<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\OrderCreationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * Requires a dedicated migrated PostgreSQL test database.
 *
 * Run with DB_CONNECTION=pgsql and the database credentials from the test
 * environment. It launches two real Laravel HTTP workers, not nested test
 * clients, so each request has an independent PostgreSQL connection.
 */
class WhatsAppPostgresConcurrencyTest extends TestCase
{
    private ?string $barrierDirectory = null;

    /** @var array<int, array{process: resource, port: int}> */
    private array $servers = [];

    private ?Outlet $outlet = null;

    private ?Product $product = null;

    private ?User $admin = null;

    private string $prefix = '';

    private ?string $orderIdentity = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql' || ! extension_loaded('pdo_pgsql')) {
            $this->markTestSkipped('PostgreSQL concurrency coverage requires DB_CONNECTION=pgsql and pdo_pgsql.');
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable $exception) {
            $this->markTestSkipped('PostgreSQL test database is unavailable: '.$exception->getMessage());
        }

        if (! DB::getSchemaBuilder()->hasTable('whatsapp_messages')) {
            $this->markTestSkipped('PostgreSQL test database is not migrated.');
        }

        $this->prefix = 'pg-race-'.bin2hex(random_bytes(8));
        $this->outlet = Outlet::factory()->create([
            'phone' => '+628'.random_int(100000000, 999999999),
            'is_active' => true,
        ]);
        $this->product = Product::factory()->create([
            'sku' => strtoupper($this->prefix),
            'name' => 'Race product',
            'stock_quantity' => 20,
            'is_active' => true,
        ]);
        $this->admin = User::factory()->admin()->create([
            'email' => $this->prefix.'@example.test',
        ]);
        config(['whatsapp.enabled' => true, 'whatsapp.webhook_secret' => $this->prefix]);
    }

    protected function tearDown(): void
    {
        $this->stopServers();
        if ($this->outlet && DB::connection()->getPdo()) {
            $orderIds = $this->orderIdentity
                ? DB::table('orders')->where('idempotency_key', $this->orderIdentity)->pluck('id')
                : collect();
            DB::table('whatsapp_messages')->where('provider_message_id', 'like', $this->prefix.'%')->delete();
            if ($orderIds->isNotEmpty()) {
                DB::table('whatsapp_messages')->whereIn('order_id', $orderIds)->delete();
                DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                DB::table('order_status_histories')->whereIn('order_id', $orderIds)->delete();
                DB::table('orders')->whereIn('id', $orderIds)->delete();
            }
            $this->admin?->delete();
            $this->product?->delete();
            $this->outlet->delete();
        }
        parent::tearDown();
    }

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

        // Limit the shared barrier to inbound locking; only the winner
        // reaches order creation after the loser observes the locked message.
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

    /** @param array<int, array{headers: array<string, string>, body: string}> $requests */
    private function runConcurrent(array $requests, string $path): array
    {
        $worker = base_path('tests/Support/http_request.php');
        $processes = [];
        $files = [];
        foreach ($requests as $index => $request) {
            $input = $this->barrierDirectory.DIRECTORY_SEPARATOR.'request-'.$index.'.json';
            $output = $this->barrierDirectory.DIRECTORY_SEPARATOR.'response-'.$index.'.json';
            file_put_contents($input, json_encode([
                'method' => 'POST',
                'url' => 'http://127.0.0.1:'.$this->servers[$index]['port'].$path,
                'headers' => $request['headers'],
                'body' => $request['body'],
            ], JSON_THROW_ON_ERROR));
            $process = proc_open([PHP_BINARY, $worker, $input, $output], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start a PostgreSQL HTTP worker.');
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            $processes[] = $process;
            $files[] = [$input, $output];
        }
        foreach ($processes as $process) {
            proc_close($process);
        }

        $responses = [];
        foreach ($files as $index => [$input, $output]) {
            $raw = json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
            $responses[$index] = $raw;
            @unlink($input);
            @unlink($output);
        }

        return $responses;
    }

    private function startServers(string $sections, bool $whatsappBarrier = true): void
    {
        $this->barrierDirectory = storage_path('framework/testing/'.$this->prefix);
        mkdir($this->barrierDirectory, 0777, true);
        $router = base_path('tests/Support/http_server.php');
        for ($index = 0; $index < 2; $index++) {
            $participant = $index === 0 ? 'A' : 'B';
            $port = $this->findFreePort();
            $environment = getenv();
            $environment['APP_ENV'] = 'testing';
            $environment['APP_KEY'] = (string) config('app.key');
            $environment['WHATSAPP_ENABLED'] = 'true';
            $environment['WHATSAPP_WEBHOOK_SECRET'] = $this->prefix;
            $environment['WHATSAPP_CONCURRENCY_BARRIER_ENABLED'] = $whatsappBarrier ? 'true' : 'false';
            if ($whatsappBarrier) {
                $environment['ORDER_CONCURRENCY_BARRIER_DIR'] = $this->barrierDirectory;
                $environment['ORDER_CONCURRENCY_BARRIER_NAME'] = $this->prefix;
                $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT'] = $participant;
            } else {
                unset(
                    $environment['ORDER_CONCURRENCY_BARRIER_DIR'],
                    $environment['ORDER_CONCURRENCY_BARRIER_NAME'],
                    $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT'],
                );
            }
            $environment['ORDER_CONCURRENCY_BARRIER_SECTIONS'] = $sections;
            $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, $router], [0 => ['pipe', 'r'], 1 => ['file', $this->barrierDirectory.'/'.$participant.'.log', 'ab'], 2 => ['file', $this->barrierDirectory.'/'.$participant.'.log', 'ab']], $pipes, base_path(), $environment);
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start a PostgreSQL HTTP server.');
            }
            fclose($pipes[0]);
            $this->servers[] = ['process' => $process, 'port' => $port];
            $this->waitForServer($port);
        }
    }

    private function waitForServer(int $port): void
    {
        $context = stream_context_create(['http' => ['timeout' => 0.25]]);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (@file_get_contents('http://127.0.0.1:'.$port.'/api/health', false, $context) !== false) {
                return;
            }
            usleep(50000);
        }
        throw new \RuntimeException('PostgreSQL HTTP server did not start.');
    }

    private function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new \RuntimeException('Unable to reserve a local port: '.$error);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr(strrchr($name, ':'), 1);
    }

    private function stopServers(): void
    {
        foreach ($this->servers as $server) {
            if (is_resource($server['process'])) {
                if (proc_get_status($server['process'])['running']) {
                    proc_terminate($server['process']);
                }
                proc_close($server['process']);
            }
        }
        $this->servers = [];
        if ($this->barrierDirectory && is_dir($this->barrierDirectory)) {
            foreach (glob($this->barrierDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->barrierDirectory);
        }
        $this->barrierDirectory = null;
    }
}
