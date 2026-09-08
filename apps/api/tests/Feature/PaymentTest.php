<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $outletUser;
    protected Outlet $outlet;
    protected string $outletToken;
    protected string $adminToken;
    protected ?string $raceDatabase = null;
    protected ?string $raceBarrierDirectory = null;
    protected array $raceServerProcesses = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->outletUser = User::factory()->outlet()->create([
            'email' => 'payment-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'is_active' => true,
        ]);
        $this->outletToken = $this->postJson('/api/auth/login', [
            'email' => 'payment-outlet@ddp.test',
            'password' => 'password123',
        ])->json('data.token');

        $admin = User::factory()->admin()->create([
            'email' => 'payment-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->postJson('/api/auth/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->json('data.token');
    }

    protected function tearDown(): void
    {
        $this->stopRaceInfrastructure();
        parent::tearDown();
    }

    protected function outletHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->outletToken}"];
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    protected function createOrder(float $total, string $identity): Order
    {
        $product = Product::factory()->create([
            'price' => $total,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        $response = $this->withHeaders($this->outletHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => $identity,
        ]);
        $response->assertStatus(201);

        return Order::findOrFail($response->json('data.id'));
    }

    protected function deliveredOrder(float $total = 100000): Order
    {
        $order = $this->createOrder($total, 'payment-order-'.uniqid());
        $order->update(['status' => 'Delivered']);
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'Delivered',
            'notes' => 'Delivered for payment test',
        ]);

        return $order->fresh();
    }

    protected function recordPayment(Order $order, float $amount, string $identity): TestResponse
    {
        return $this->withHeaders($this->adminHeaders())->postJson('/api/payments', [
            'order_id' => $order->id,
            'amount' => $amount,
            'payment_method' => 'cash',
            'idempotency_key' => $identity,
        ]);
    }

    protected function setCreditLimit(float $amount): void
    {
        if (!Schema::hasTable('credit_limits')) {
            Schema::create('credit_limits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('outlet_id')->unique();
                $table->decimal('limit_amount', 14, 2);
                $table->timestamps();
            });
        }

        DB::table('credit_limits')->updateOrInsert(
            ['outlet_id' => $this->outlet->id],
            ['limit_amount' => $amount, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /**
     * RED CYCLE 1: Given order is delivered, when recording payment, then the
     * payment is recorded and the order balance is updated.
     */
    public function test_admin_can_record_payment_for_delivered_order(): void
    {
        $this->mock(ReceiptService::class, function ($mock) {
            $mock->shouldReceive('generate')->once()->andReturn('RCT-MOCKED');
        });
        $order = $this->deliveredOrder(100000);

        $response = $this->withHeaders($this->adminHeaders())->postJson('/api/payments', [
            'order_id' => $order->order_id,
            'amount' => 40000,
            'payment_method' => 'cash',
            'idempotency_key' => 'payment-'.$order->id.'-1',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.amount', '40000.00')
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.order.outstanding_balance', '60000.00')
            ->assertJsonPath('data.receipt.reference', 'RCT-MOCKED')
            ->assertJsonPath('data.receipt.items.0.quantity', 1);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'amount' => 40000,
            'payment_method' => 'cash',
        ]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'Partially Paid',
        ]);
    }

    /**
     * RED CYCLE 2: Given an outlet has pending orders exceeding its credit,
     * when placing another order, the submission is rejected before mutation.
     */
    public function test_order_is_blocked_when_credit_limit_would_be_exceeded(): void
    {
        $this->withHeaders($this->adminHeaders())
            ->putJson('/api/admin/outlets/'.$this->outlet->id.'/credit-limit', ['limit_amount' => 50000])
            ->assertStatus(200)
            ->assertJsonPath('data.credit_limit', '50000.00');
        $existingProduct = Product::factory()->create([
            'price' => 40000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);
        $newProduct = Product::factory()->create([
            'price' => 20000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        $existing = $this->withHeaders($this->outletHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $existingProduct->id, 'quantity' => 1]],
            'idempotency_key' => 'credit-existing',
        ]);
        $existing->assertStatus(201);
        $beforeCount = Order::count();
        $beforeStock = $newProduct->fresh()->stock_quantity;

        $response = $this->withHeaders($this->outletHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $newProduct->id, 'quantity' => 1]],
            'idempotency_key' => 'credit-over-limit',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 'error');
        $this->assertSame($beforeCount, Order::count());
        $this->assertSame($beforeStock, $newProduct->fresh()->stock_quantity);
    }

    public function test_partial_payments_have_non_negative_balance_and_replay_is_idempotent(): void
    {
        $order = $this->deliveredOrder(100000);

        $this->recordPayment($order, 30000, 'partial-1')->assertStatus(201);
        $replay = $this->recordPayment($order, 30000, 'partial-1');
        $replay->assertStatus(200)->assertJsonPath('data.order.outstanding_balance', '70000.00');
        $this->recordPayment($order, 30001, 'partial-1')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
        $otherOrder = $this->deliveredOrder(100000);
        $this->recordPayment($otherOrder, 30000, 'partial-1')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
        $this->recordPayment($order, 70001, 'partial-overpayment')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
        $this->recordPayment($order, 70000, 'partial-2')
            ->assertStatus(201)
            ->assertJsonPath('data.order.outstanding_balance', '0.00');
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Paid', 'paid_amount' => 100000]);
    }

    /**
     * Two independent public HTTP workers submit one payment identity at the
     * same time. Both responses must replay the committed payment rather than
     * exposing the unique-key race as a server error.
     */
    public function test_concurrent_same_identity_payment_posts_replay_one_payment(): void
    {
        $this->preparePaymentRaceDatabase();
        [$admin, $outlet] = $this->createPaymentRaceOutletAndAdmin();
        $order = Order::on('race')->create([
            'order_id' => 'ORD-RACE-PAYMENT',
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 100000,
            'commission_percentage' => 2.00,
            'idempotency_key' => 'race-payment-order',
        ]);
        OrderStatusHistory::on('race')->create([
            'order_id' => $order->id,
            'status' => 'Delivered',
            'notes' => 'Delivered for payment race test',
        ]);

        $this->startPaymentRaceServers('payment');
        $token = $this->paymentRaceLogin($admin->email);
        $payload = [
            'order_id' => $order->id,
            'amount' => 40000,
            'payment_method' => 'cash',
            'idempotency_key' => 'concurrent-payment-key',
        ];

        $responses = $this->runConcurrentPaymentHttpRequests([
            ['token' => $token, 'body' => $payload],
            ['token' => $token, 'body' => $payload],
        ]);
        $statuses = array_column($responses, 'status');
        sort($statuses);

        $this->assertSame([200, 201], $statuses);
        $this->assertSame(['success', 'success'], array_column(array_column($responses, 'json'), 'status'));
        $this->assertSame(
            $responses[0]['json']['data']['id'],
            $responses[1]['json']['data']['id']
        );
        $this->assertSame(1, Payment::on('race')->count());
        $this->assertSame('40000.00', (string) Payment::on('race')->sole()->amount);
        $this->assertSame('40000.00', (string) Order::on('race')->findOrFail($order->id)->paid_amount);
        $this->assertSame('Partially Paid', Order::on('race')->findOrFail($order->id)->status);
    }

    public function test_outstanding_balance_includes_pending_and_unpaid_orders_but_excludes_paid_orders(): void
    {
        $new = $this->createOrder(10000, 'outstanding-new');
        $confirmed = $this->createOrder(20000, 'outstanding-confirmed');
        $confirmed->update(['status' => 'Confirmed']);
        $delivered = $this->deliveredOrder(30000);
        $paid = $this->deliveredOrder(40000);
        $this->recordPayment($paid, 40000, 'outstanding-paid')->assertStatus(201);

        $this->setCreditLimit(200000);
        $response = $this->withHeaders($this->outletHeaders())->getJson('/api/credit-limit');
        $response->assertStatus(200)
            ->assertJsonPath('data.credit_limit', '200000.00')
            ->assertJsonPath('data.outstanding_balance', '60000.00')
            ->assertJsonPath('data.available_credit', '140000.00');

        $this->assertSame('New', $new->fresh()->status);
        $this->assertSame('Confirmed', $confirmed->fresh()->status);
        $this->assertSame('Delivered', $delivered->fresh()->status);
        $this->assertSame('Paid', $paid->fresh()->status);
    }

    public function test_zero_credit_limit_rejects_order_before_order_or_stock_mutation(): void
    {
        $this->setCreditLimit(0);
        $product = Product::factory()->create(['price' => 1, 'stock_quantity' => 4, 'is_active' => true]);
        $before = Order::count();

        $response = $this->withHeaders($this->outletHeaders())->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => 'zero-limit',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['credit_limit']);
        $this->assertSame($before, Order::count());
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_payment_rejects_unauthorized_order_and_invalid_status_or_amount(): void
    {
        $order = $this->deliveredOrder(100000);
        $otherUser = User::factory()->outlet()->create([
            'email' => 'other-payment-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        Outlet::factory()->create(['user_id' => $otherUser->id]);
        $otherToken = $this->postJson('/api/auth/login', [
            'email' => $otherUser->email,
            'password' => 'password123',
        ])->json('data.token');

        $this->withHeaders(['Authorization' => "Bearer {$otherToken}"])
            ->postJson('/api/payments', [
                'order_id' => $order->id,
                'amount' => 1,
                'payment_method' => 'cash',
                'idempotency_key' => 'unauthorized-payment',
            ])->assertStatus(403);

        $this->recordPayment($order, 1, 'replay-auth')->assertStatus(201);
        $this->withHeaders(['Authorization' => "Bearer {$otherToken}"])
            ->postJson('/api/payments', [
                'order_id' => $order->id,
                'amount' => 1,
                'payment_method' => 'cash',
                'idempotency_key' => 'replay-auth',
            ])->assertStatus(403);

        $this->recordPayment($order, 100001, 'over-payment')->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $newOrder = $this->createOrder(10000, 'invalid-payment-status');
        $this->recordPayment($newOrder, 1, 'invalid-status')->assertStatus(422)->assertJsonValidationErrors(['order_id']);
        $this->recordPayment($order, 0, 'zero-payment')->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    /** @return array{0: User, 1: Outlet} */
    private function createPaymentRaceOutletAndAdmin(): array
    {
        $admin = User::factory()->admin()->make([
            'email' => 'payment-race-admin@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->setConnection('race');
        $admin->save();

        $outletUser = User::factory()->outlet()->make([
            'email' => 'payment-race-outlet@ddp.test',
            'password' => Hash::make('password123'),
        ]);
        $outletUser->setConnection('race');
        $outletUser->save();
        $outlet = Outlet::factory()->make([
            'user_id' => $outletUser->id,
            'is_active' => true,
        ]);
        $outlet->setConnection('race');
        $outlet->save();

        return [$admin, $outlet];
    }

    private function preparePaymentRaceDatabase(): void
    {
        $directory = storage_path('framework/testing');
        foreach (['views', 'cache', 'sessions'] as $subdirectory) {
            $path = storage_path('framework/'.$subdirectory);
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $this->raceDatabase = tempnam($directory, 'payment-race-');
        if ($this->raceDatabase === false) {
            throw new \RuntimeException('Unable to create the payment race database file.');
        }

        config(['database.connections.race' => array_merge(
            config('database.connections.sqlite'),
            ['database' => $this->raceDatabase],
        )]);
        DB::purge('race');
        $this->artisan('migrate:fresh', ['--database' => 'race', '--force' => true]);
    }

    private function startPaymentRaceServers(string $section): void
    {
        $this->raceBarrierDirectory = storage_path('framework/testing/payment-barrier-'.bin2hex(random_bytes(8)));
        mkdir($this->raceBarrierDirectory, 0777, true);
        $serverRouter = base_path('tests/Support/http_server.php');

        for ($index = 0; $index < 2; $index++) {
            $participant = $index === 0 ? 'A' : 'B';
            $port = $this->findPaymentRaceFreePort();
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
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start the payment race server.');
            }
            fclose($pipes[0]);

            $this->raceServerProcesses[] = [
                'process' => $process,
                'port' => $port,
                'log' => $log,
            ];
            $this->waitForPaymentRaceServer($port, $log);
        }
    }

    private function waitForPaymentRaceServer(int $port, string $log): void
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
        throw new \RuntimeException("Payment race server did not start: {$details}");
    }

    private function paymentRaceLogin(string $email): string
    {
        $response = $this->paymentRaceHttpRequest(
            'POST',
            $this->paymentRaceUrl(0).'/api/auth/login',
            ['email' => $email, 'password' => 'password123'],
        );
        if (($response['status'] ?? 0) !== 200 || ! isset($response['json']['data']['token'])) {
            throw new \RuntimeException('Payment race server login failed: '.json_encode($response));
        }

        return $response['json']['data']['token'];
    }

    /** @param array<int, array{token: string, body: array<string, mixed>}> $requests */
    private function runConcurrentPaymentHttpRequests(array $requests): array
    {
        $worker = base_path('tests/Support/http_request.php');
        $processes = [];
        $files = [];
        foreach ($requests as $index => $request) {
            $input = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'request-'.$index.'.json';
            $output = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'response-'.$index.'.json';
            file_put_contents($input, json_encode([
                'method' => 'POST',
                'url' => $this->paymentRaceUrl($index).'/api/payments',
                'headers' => ['Authorization' => 'Bearer '.$request['token']],
                'body' => json_encode($request['body'], JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR));
            $process = proc_open([PHP_BINARY, $worker, $input, $output], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, base_path());
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start a payment HTTP worker.');
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
            if (! is_file($output)) {
                throw new \RuntimeException("Payment HTTP worker {$index} produced no response.");
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
    private function paymentRaceHttpRequest(string $method, string $url, array $body): array
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

    private function paymentRaceUrl(int $index): string
    {
        return 'http://127.0.0.1:'.$this->raceServerProcesses[$index]['port'];
    }

    private function findPaymentRaceFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new \RuntimeException("Unable to reserve a payment race port: {$error}");
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr(strrchr($name, ':'), 1);
    }

    private function stopRaceInfrastructure(): void
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
            DB::purge('race');
            @unlink($this->raceDatabase);
            $this->raceDatabase = null;
        }
    }
}
