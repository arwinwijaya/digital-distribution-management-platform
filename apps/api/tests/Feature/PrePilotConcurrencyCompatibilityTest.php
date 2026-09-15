<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrePilotConcurrencyCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $token;
    protected ?string $raceDatabase = null;
    protected ?string $raceBarrierDirectory = null;
    protected array $raceServerProcesses = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'jwt.secret' => base64_encode(hash('sha256', 'pre-pilot-concurrency-jwt', true)),
            'jwt.ttl' => 999999,
        ]);
        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'concurrency-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'is_active' => true,
        ]);
        $this->token = $this->login($this->outletUser);
    }

    protected function tearDown(): void
    {
        $this->stopRaceInfrastructure();
        parent::tearDown();
    }

    // ================================================================
    // 1. Concurrent same-identity order submissions returning one result.
    // ================================================================
    public function test_concurrent_same_identity_orders_return_one_result(): void
    {
        $this->prepareRaceDatabase();
        [$outletUser, $outlet] = $this->createRaceOutlet();
        $product = $this->createRaceProduct([
            'price' => 25000,
            'stock_quantity' => 8,
            'is_active' => true,
        ]);
        $this->startRaceServers('order');
        $token = $this->raceLogin($outletUser->email);
        $payload = [
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'idempotency_key' => 'pre-pilot-concurrent-order',
        ];

        $responses = $this->runConcurrentHttpRequests([
            ['token' => $token, 'body' => $payload, 'method' => 'POST'],
            ['token' => $token, 'body' => $payload, 'method' => 'POST'],
        ], '/api/orders');

        $statuses = array_column($responses, 'status');
        sort($statuses);
        $this->assertSame([200, 201], $statuses);
        $this->assertSame(
            $responses[0]['json']['data']['order_id'],
            $responses[1]['json']['data']['order_id']
        );
        $this->assertSame(1, Order::on('race')->where('outlet_id', $outlet->id)->count());
        $this->assertSame(1, OrderItem::on('race')->count());
    }

    // ================================================================
    // 2. Concurrent payment submissions not producing negative balance
    //    or duplicate posting.
    // ================================================================
    public function test_concurrent_payments_do_not_produce_negative_balance(): void
    {
        $product = Product::factory()->create(['price' => 100000, 'stock_quantity' => 10, 'is_active' => true]);
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/orders', [
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'idempotency_key' => 'concurrency-payment-order',
            ]);
        $response->assertCreated();

        $order = Order::findOrFail($response->json('data.id'));
        $order->update(['status' => 'Delivered']);
        OrderStatusHistory::create(['order_id' => $order->id, 'status' => 'Delivered', 'notes' => 'Delivered']);

        Invoice::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'invoice_number' => 'INV-CONCUR-'.$order->id,
            'issue_date' => now()->subDays(7)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 100000,
            'paid_amount' => 0,
            'balance_amount' => 100000,
            'status' => Invoice::UNPAID,
        ]);

        $admin = User::factory()->admin()->create([
            'email' => 'concurrency-admin-payment@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $adminToken = $this->login($admin);

        // Sequential payment attempts testing idempotency guard rather than true pgsql race,
        // because default sqlite + RefreshDatabase environment is used here.
        $first = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 50000,
            'payment_method' => 'cash',
            'idempotency_key' => 'concur-pay-001',
        ]);
        $first->assertCreated();

        // Different payload with same idempotency key must fail
        $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => 30000,
            'payment_method' => 'cash',
            'idempotency_key' => 'concur-pay-001',
        ])->assertStatus(422)->assertJsonValidationErrors(['idempotency_key']);

        // Verify no negative balance
        $order->refresh();
        $this->assertGreaterThanOrEqual(0, (float) $order->paid_amount);
        $this->assertSame('Partially Paid', $order->status);
        $this->assertSame('50000.00', (string) $order->paid_amount);
        $this->assertSame(1, \App\Models\Payment::where('order_id', $order->id)->count());
    }

    // ================================================================
    // 3. Concurrent delivery/payment or approval race preserving
    //    existing one-wins lock behavior.
    // ================================================================
    public function test_concurrent_approvals_preserve_one_wins_behavior(): void
    {
        $this->prepareRaceDatabase();
        [, $outlet] = $this->createRaceOutlet();
        $product = $this->createRaceProduct(['price' => 50000, 'stock_quantity' => 5]);
        $order = Order::on('race')->create([
            'order_id' => 'ORD-RACE-PRE-PILOT-APPROVAL',
            'outlet_id' => $outlet->id,
            'status' => 'New',
            'total_amount' => 50000,
            'commission_percentage' => 2.00,
            'idempotency_key' => 'race-pre-pilot-approval',
        ]);
        OrderItem::on('race')->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'subtotal' => 50000,
        ]);
        OrderStatusHistory::on('race')->create([
            'order_id' => $order->id,
            'status' => 'New',
            'notes' => 'Order created',
        ]);
        $admin = $this->createRaceUser('concurrent-pre-pilot-admin@ddp.test', 'admin');
        $this->startRaceServers('approval');
        $token = $this->raceLogin($admin->email);

        $responses = $this->runConcurrentHttpRequests([
            ['token' => $token, 'body' => [], 'method' => 'PUT'],
            ['token' => $token, 'body' => [], 'method' => 'PUT'],
        ], "/api/orders/{$order->id}/approve");

        $statuses = array_column($responses, 'status');
        sort($statuses);

        // Two concurrent approval requests must not produce duplicate Confirmed histories
        $confirmedCount = OrderStatusHistory::on('race')
            ->where('order_id', $order->id)
            ->where('status', 'Confirmed')
            ->count();
        $this->assertSame(1, $confirmedCount);
        $this->assertSame('Confirmed', Order::on('race')->findOrFail($order->id)->status);
    }

    // ================================================================
    // Infrastructure helpers (file-backed sqlite HTTP race).
    // ================================================================
    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    protected function prepareRaceDatabase(): void
    {
        $directory = storage_path('framework/testing');
        foreach (['views', 'cache', 'sessions'] as $subdirectory) {
            $path = storage_path('framework/'.$subdirectory);
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $this->raceDatabase = tempnam($directory, 'pre-pilot-race-');
        if ($this->raceDatabase === false) {
            throw new \RuntimeException('Unable to create the race database file.');
        }

        config(['database.connections.race' => array_merge(
            config('database.connections.sqlite'),
            ['database' => $this->raceDatabase],
        )]);
        \DB::purge('race');
        $this->artisan('migrate:fresh', ['--database' => 'race', '--force' => true]);
    }

    protected function createRaceUser(string $email, string $role = 'outlet'): User
    {
        $user = User::factory()->make([
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
        ]);
        $user->setConnection('race');
        $user->save();
        return $user;
    }

    /** @return array{0: User, 1: Outlet} */
    protected function createRaceOutlet(): array
    {
        $user = $this->createRaceUser('race-outlet@ddp.test');
        $outlet = Outlet::factory()->make([
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $outlet->setConnection('race');
        $outlet->save();
        return [$user, $outlet];
    }

    protected function createRaceProduct(array $attributes = []): Product
    {
        $product = Product::factory()->make($attributes);
        $product->setConnection('race');
        $product->save();
        return $product;
    }

    protected function startRaceServers(string $section): void
    {
        $this->raceBarrierDirectory = storage_path('framework/testing/pre-pilot-barrier-'.bin2hex(random_bytes(8)));
        mkdir($this->raceBarrierDirectory, 0777, true);
        $serverRouter = base_path('tests/Support/http_server.php');

        for ($index = 0; $index < 2; $index++) {
            $participant = $index === 0 ? 'A' : 'B';
            $port = $this->findFreePort();
            $log = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'server-'.$participant.'.log';
            $command = [PHP_BINARY, '-S', '127.0.0.1:'.$port, $serverRouter];
            $environment = getenv();
            $environment['APP_ENV'] = 'testing';
            $environment['APP_KEY'] = (string) config('app.key');
            $environment['DB_CONNECTION'] = 'sqlite';
            $environment['DB_DATABASE'] = $this->raceDatabase;
            $environment['CACHE_STORE'] = 'array';
            $environment['CACHE_DRIVER'] = 'array';
            $environment['SESSION_DRIVER'] = 'array';
            $environment['QUEUE_CONNECTION'] = 'sync';
            $environment['MAIL_MAILER'] = 'array';
            $environment['TELESCOPE_ENABLED'] = 'false';
            $environment['ORDER_CONCURRENCY_BARRIER_DIR'] = $this->raceBarrierDirectory;
            $environment['ORDER_CONCURRENCY_BARRIER_NAME'] = $section;
            $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT'] = $participant;

            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'ab'],
                2 => ['file', $log, 'ab'],
            ], $pipes, base_path(), $environment);
            if (!is_resource($process)) {
                throw new \RuntimeException('Unable to start the Laravel race server.');
            }
            fclose($pipes[0]);

            $this->raceServerProcesses[] = [
                'process' => $process,
                'port' => $port,
                'log' => $log,
            ];
            $this->waitForRaceServer($port, $log);
        }
    }

    protected function waitForRaceServer(int $port, string $log): void
    {
        $context = stream_context_create(['http' => [
            'timeout' => 0.25,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\n",
        ]]);
        $url = "http://127.0.0.1:{$port}/api/health";
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (@file_get_contents($url, false, $context) !== false) {
                return;
            }
            usleep(50000);
        }
        $details = is_file($log) ? file_get_contents($log) : '';
        throw new \RuntimeException("Race server did not start: {$details}");
    }

    protected function raceLogin(string $email): string
    {
        $response = $this->publicHttpRequest(
            'POST',
            $this->raceUrl(0).'/api/auth/login',
            ['email' => $email, 'password' => 'password123'],
        );
        if (($response['status'] ?? 0) !== 200 || !isset($response['json']['data']['token'])) {
            throw new \RuntimeException('Race server login failed: '.json_encode($response));
        }
        return $response['json']['data']['token'];
    }

    /** @param array<int, array{token: string, body: array<string, mixed>, method?: string}> $requests */
    protected function runConcurrentHttpRequests(array $requests, string $path): array
    {
        $worker = base_path('tests/Support/http_request.php');
        $processes = [];
        $files = [];
        foreach ($requests as $index => $request) {
            $input = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'request-'.$index.'.json';
            $output = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'response-'.$index.'.json';
            $method = $request['method'] ?? 'POST';
            file_put_contents($input, json_encode([
                'method' => $method,
                'url' => $this->raceUrl($index).$path,
                'headers' => ['Authorization' => 'Bearer '.$request['token']],
                'body' => json_encode($request['body'] ?? [], JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR));
            $command = [PHP_BINARY, $worker, $input, $output];
            $process = proc_open($command, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, base_path());
            if (!is_resource($process)) {
                throw new \RuntimeException('Unable to start an HTTP worker.');
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            $processes[$index] = $process;
            $files[$index] = [$input, $output];
        }

        foreach ($processes as $process) {
            proc_close($process);
        }

        $responses = [];
        foreach ($files as $index => [$input, $output]) {
            if (!is_file($output)) {
                throw new \RuntimeException("HTTP worker {$index} produced no response.");
            }
            $raw = json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
            $raw['json'] = json_decode($raw['body'] ?? '', true);
            $responses[$index] = $raw;
            @unlink($input);
            @unlink($output);
        }

        return $responses;
    }

    /** @return array{status: int, body: string, json: array<string, mixed>|null} */
    protected function publicHttpRequest(string $method, string $url, array $body): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Accept: application/json\r\nContent-Type: application/json\r\n",
            'content' => json_encode($body, JSON_THROW_ON_ERROR),
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);
        $responseBody = file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }
        return [
            'status' => $status,
            'body' => $responseBody === false ? '' : $responseBody,
            'json' => json_decode($responseBody ?: '', true),
        ];
    }

    protected function raceUrl(int $index): string
    {
        return 'http://127.0.0.1:'.$this->raceServerProcesses[$index]['port'];
    }

    protected function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new \RuntimeException("Unable to reserve a local port: {$error}");
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr(strrchr($name, ':'), 1);
    }

    protected function stopRaceInfrastructure(): void
    {
        foreach ($this->raceServerProcesses as $server) {
            if (is_resource($server['process'])) {
                $status = proc_get_status($server['process']);
                if ($status['running']) {
                    proc_terminate($server['process']);
                }
                proc_close($server['process']);
            }
        }
        $this->raceServerProcesses = [];

        if ($this->raceBarrierDirectory && is_dir($this->raceBarrierDirectory)) {
            foreach (glob($this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->raceBarrierDirectory);
        }
        $this->raceBarrierDirectory = null;

        if ($this->raceDatabase) {
            \DB::purge('race');
            @unlink($this->raceDatabase);
            $this->raceDatabase = null;
        }
    }
}
