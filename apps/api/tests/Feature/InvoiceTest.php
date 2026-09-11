<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvoiceTest extends TestCase
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
            'email' => 'invoice-outlet@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->outlet = Outlet::factory()->create([
            'user_id' => $this->outletUser->id,
            'is_active' => true,
        ]);
        $this->outletToken = $this->login($this->outletUser);

        $admin = User::factory()->admin()->create([
            'email' => 'invoice-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $this->adminToken = $this->login($admin);
    }

    protected function tearDown(): void
    {
        $this->stopRaceInfrastructure();
        parent::tearDown();
    }

    public function test_approved_order_creates_invoice_with_configured_term(): void
    {
        $this->outlet->update(['payment_term_days' => 14]);
        $order = $this->createOrder('configured-term');

        $this->approve($order)->assertOk();

        $invoice = Invoice::where('order_id', $order->id)->sole();
        $this->assertEquals(14, Carbon::parse($invoice->issue_date)->diffInDays(Carbon::parse($invoice->due_date)));
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame((string) $order->total_amount, (string) $invoice->balance_amount);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_missing_payment_term_uses_seven_day_fallback(): void
    {
        $this->outlet->update(['payment_term_days' => null]);
        $order = $this->createOrder('fallback-term');

        $this->approve($order)->assertOk();

        $invoice = Invoice::where('order_id', $order->id)->sole();
        $this->assertEquals(7, Carbon::parse($invoice->issue_date)->diffInDays(Carbon::parse($invoice->due_date)));
    }

    public function test_term_changes_affect_new_invoices_only(): void
    {
        $this->outlet->update(['payment_term_days' => 14]);
        $firstOrder = $this->createOrder('first-term');
        $this->approve($firstOrder)->assertOk();
        $firstDueDate = Invoice::where('order_id', $firstOrder->id)->value('due_date');

        $this->outlet->update(['payment_term_days' => 30]);
        $secondOrder = $this->createOrder('second-term');
        $this->approve($secondOrder)->assertOk();

        $this->assertEquals($firstDueDate, Invoice::where('order_id', $firstOrder->id)->value('due_date'));
        $second = Invoice::where('order_id', $secondOrder->id)->sole();
        $this->assertEquals(30, Carbon::parse($second->issue_date)->diffInDays(Carbon::parse($second->due_date)));
    }

    public function test_approval_retry_reuses_invoice(): void
    {
        $order = $this->createOrder('approval-retry');
        $this->approve($order)->assertOk();

        $retry = $this->approve($order);

        $retry->assertOk()->assertJsonPath('status', 'success');
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->where('status', 'Confirmed')->count());
    }

    public function test_concurrent_approvals_create_one_invoice(): void
    {
        $this->prepareRaceDatabase();
        $order = $this->createRaceOrder();
        $this->startRaceServers();
        $token = $this->raceLogin('invoice-race-admin@example.test');

        $responses = $this->runConcurrentApprovalRequests($order->id, $token);
        $this->assertSame([200, 200], array_values(array_column($responses, 'status')));
        $this->assertSame(1, Invoice::on('race')->where('order_id', $order->id)->count());
        $this->assertSame(1, OrderStatusHistory::on('race')->where('order_id', $order->id)->where('status', 'Confirmed')->count());
    }

    public function test_invoice_history_is_bounded_and_scoped(): void
    {
        $this->outlet->update(['payment_term_days' => 14]);
        $first = $this->createOrder('history-a-1');
        $second = $this->createOrder('history-a-2');
        $this->approve($first)->assertOk();
        $this->approve($second)->assertOk();

        $otherUser = User::factory()->outlet()->create([
            'email' => 'invoice-other@example.test',
            'password' => Hash::make('password123'),
        ]);
        $otherOutlet = Outlet::factory()->create(['user_id' => $otherUser->id]);
        $otherOrder = $this->createOrderForOutlet($otherOutlet, 'history-b-1');
        $this->approve($otherOrder)->assertOk();

        $response = $this->withToken($this->outletToken)->getJson('/api/invoices?page=1&limit=1');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.limit', 1)
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonCount(1, 'data');
        $this->assertSame($this->outlet->id, $response->json('data.0.outlet_id'));
    }

    public function test_invalid_invoice_pagination_is_rejected(): void
    {
        $this->withToken($this->outletToken)
            ->getJson('/api/invoices?page=0&limit=101')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['page', 'limit']);
    }

    public function test_admin_can_cancel_unpaid_invoice(): void
    {
        $order = $this->createOrder('cancel-unpaid');
        $this->approve($order)->assertOk();

        $this->withToken($this->adminToken)
            ->putJson("/api/orders/{$order->id}/cancel")
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Cancelled']);
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id, 'status' => Invoice::CANCELLED]);
    }

    public function test_payment_row_prevents_cancellation(): void
    {
        $order = $this->createOrder('cancel-payment');
        $this->approve($order)->assertOk();
        Payment::create([
            'order_id' => $order->id,
            'outlet_id' => $order->outlet_id,
            'amount' => 1,
            'payment_method' => 'cash',
            'status' => 'pending',
            'idempotency_key' => 'cancel-payment-row',
        ]);

        $this->withToken($this->adminToken)
            ->putJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(409);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Confirmed']);
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id, 'status' => Invoice::UNPAID]);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id]);
    }

    public function test_non_admin_cannot_cancel_order(): void
    {
        $order = $this->createOrder('cancel-forbidden');
        $this->approve($order)->assertOk();

        $this->withToken($this->outletToken)
            ->putJson("/api/orders/{$order->id}/cancel")
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'Confirmed']);
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id, 'status' => Invoice::UNPAID]);
    }

    protected function login(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk()->json('data.token');
    }

    protected function createOrder(string $identity): Order
    {
        return $this->createOrderForOutlet($this->outlet, $identity);
    }

    protected function createOrderForOutlet(Outlet $outlet, string $identity): Order
    {
        $product = Product::factory()->create([
            'price' => 100000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        $user = $outlet->user;
        $token = $user && $user->exists ? ($user->id === $this->outletUser->id ? $this->outletToken : $this->login($user)) : $this->outletToken;
        $response = $this->withToken($token)->postJson('/api/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'idempotency_key' => $identity,
        ])->assertCreated();

        return Order::findOrFail($response->json('data.id'));
    }

    protected function approve(Order $order): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->adminToken)->putJson("/api/orders/{$order->id}/approve");
    }

    protected function prepareRaceDatabase(): void
    {
        $directory = storage_path('framework/testing');
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        $this->raceDatabase = tempnam($directory, 'invoice-race-');
        if ($this->raceDatabase === false) {
            throw new \RuntimeException('Unable to create the invoice race database.');
        }

        config(['database.connections.race' => array_merge(
            config('database.connections.sqlite'),
            ['database' => $this->raceDatabase],
        )]);
        DB::purge('race');
        $this->artisan('migrate:fresh', ['--database' => 'race', '--force' => true]);
    }

    protected function createRaceOrder(): Order
    {
        $admin = User::factory()->admin()->make([
            'email' => 'invoice-race-admin@example.test',
            'password' => Hash::make('password123'),
        ]);
        $admin->setConnection('race');
        $admin->save();

        $outletUser = User::factory()->outlet()->make([
            'email' => 'invoice-race-outlet@example.test',
            'password' => Hash::make('password123'),
        ]);
        $outletUser->setConnection('race');
        $outletUser->save();

        $outlet = Outlet::factory()->make(['user_id' => $outletUser->id]);
        $outlet->setConnection('race');
        $outlet->save();

        $product = Product::factory()->make([
            'price' => 100000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        $product->setConnection('race');
        $product->save();

        $order = new Order([
            'order_id' => 'ORD-INVOICE-RACE',
            'outlet_id' => $outlet->id,
            'status' => 'New',
            'total_amount' => 100000,
            'commission_percentage' => 2.00,
            'idempotency_key' => 'invoice-race-order',
        ]);
        $order->setConnection('race');
        $order->save();

        $history = new OrderStatusHistory([
            'order_id' => $order->id,
            'status' => 'New',
            'notes' => 'Invoice race fixture',
        ]);
        $history->setConnection('race');
        $history->save();

        $item = new OrderItem([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'subtotal' => 100000,
        ]);
        $item->setConnection('race');
        $item->save();

        return $order;
    }

    protected function startRaceServers(): void
    {
        $this->raceBarrierDirectory = storage_path('framework/testing/invoice-barrier-'.bin2hex(random_bytes(8)));
        mkdir($this->raceBarrierDirectory, 0777, true);
        $router = base_path('tests/Support/http_server.php');

        for ($index = 0; $index < 2; $index++) {
            $participant = $index === 0 ? 'A' : 'B';
            $port = $this->findRaceFreePort();
            $log = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'server-'.$participant.'.log';
            $environment = getenv();
            $environment['APP_ENV'] = 'testing';
            $environment['APP_KEY'] = (string) config('app.key');
            $environment['DB_CONNECTION'] = 'sqlite';
            $environment['DB_DATABASE'] = $this->raceDatabase;
            $environment['CACHE_STORE'] = 'array';
            $environment['CACHE_DRIVER'] = 'array';
            $environment['SESSION_DRIVER'] = 'array';
            $environment['QUEUE_CONNECTION'] = 'sync';
            $environment['ORDER_CONCURRENCY_BARRIER_DIR'] = $this->raceBarrierDirectory;
            $environment['ORDER_CONCURRENCY_BARRIER_NAME'] = 'approval';
            $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT'] = $participant;
            $environment['ORDER_CONCURRENCY_BARRIER_SECTIONS'] = 'approval';

            $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, $router], [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'ab'],
                2 => ['file', $log, 'ab'],
            ], $pipes, base_path(), $environment);
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start invoice race server.');
            }
            fclose($pipes[0]);
            $this->raceServerProcesses[] = ['process' => $process, 'port' => $port, 'log' => $log];
            $this->waitForRaceServer($port, $log);
        }
    }

    /** @return array<int, array{status: int, body: string, json: array<string, mixed>|null}> */
    protected function runConcurrentApprovalRequests(int $orderId, string $token): array
    {
        $processes = [];
        $files = [];
        $worker = base_path('tests/Support/http_request.php');
        foreach ([0, 1] as $index) {
            $input = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'request-'.$index.'.json';
            $output = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'response-'.$index.'.json';
            file_put_contents($input, json_encode([
                'method' => 'PUT',
                'url' => $this->raceUrl($index).'/api/orders/'.$orderId.'/approve',
                'headers' => ['Authorization' => 'Bearer '.$token],
                'body' => '{}',
            ], JSON_THROW_ON_ERROR));
            $process = proc_open([PHP_BINARY, $worker, $input, $output], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes, base_path());
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start invoice HTTP worker.');
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
                throw new \RuntimeException("Invoice HTTP worker {$index} produced no response.");
            }
            $raw = json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
            $raw['json'] = json_decode($raw['body'] ?? '', true);
            $responses[] = $raw;
            @unlink($input);
            @unlink($output);
        }

        return $responses;
    }

    protected function raceLogin(string $email): string
    {
        $response = $this->raceHttpRequest('POST', $this->raceUrl(0).'/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);
        if (($response['status'] ?? 0) !== 200 || ! isset($response['json']['data']['token'])) {
            throw new \RuntimeException('Invoice race login failed: '.json_encode($response));
        }

        return $response['json']['data']['token'];
    }

    protected function waitForRaceServer(int $port, string $log): void
    {
        $context = stream_context_create(['http' => ['timeout' => 0.25, 'ignore_errors' => true]]);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (@file_get_contents('http://127.0.0.1:'.$port.'/api/health', false, $context) !== false) {
                return;
            }
            usleep(50000);
        }
        throw new \RuntimeException('Invoice race server did not start: '.(is_file($log) ? file_get_contents($log) : ''));
    }

    /** @return array{status: int, body: string, json: array<string, mixed>|null} */
    protected function raceHttpRequest(string $method, string $url, array $body): array
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

    protected function findRaceFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new \RuntimeException("Unable to reserve invoice race port: {$error}");
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
            DB::purge('race');
            @unlink($this->raceDatabase);
            $this->raceDatabase = null;
        }
    }
}
