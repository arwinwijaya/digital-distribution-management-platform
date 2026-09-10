<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeliveryConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected ?string $raceDatabase = null;
    protected ?string $raceBarrierDirectory = null;
    protected array $raceServerProcesses = [];

    protected function tearDown(): void
    {
        $this->stopRaceInfrastructure();
        parent::tearDown();
    }

    /**
     * Completion and a payment status mutation use independent public HTTP
     * workers. The shared barrier releases both transactions before they
     * contend for the order row; whichever valid transition wins leaves a
     * status/history pair that agrees, with exactly one Delivered entry.
     */
    public function test_concurrent_delivery_completion_and_payment_keep_order_history_consistent(): void
    {
        $this->prepareRaceDatabase();
        $outletUser = $this->createRaceUser('delivery-race-outlet@example.com', 'outlet');
        $outlet = Outlet::factory()->make(['user_id' => $outletUser->id, 'is_active' => true]);
        $outlet->setConnection('race');
        $outlet->save();
        $driver = $this->createRaceUser('delivery-race-driver@example.com', 'driver');
        $admin = $this->createRaceUser('delivery-race-admin@example.com', 'admin');
        $order = Order::on('race')->create([
            'order_id' => 'ORD-RACE-DELIVERY',
            'outlet_id' => $outlet->id,
            'status' => 'Confirmed',
            'total_amount' => 100000,
            'idempotency_key' => 'delivery-race-order',
        ]);
        OrderStatusHistory::on('race')->create([
            'order_id' => $order->id,
            'status' => 'Confirmed',
            'notes' => 'Order approved',
        ]);
        $delivery = Delivery::on('race')->create([
            'order_id' => $order->id,
            'driver_id' => $driver->id,
            'assigned_by_id' => $admin->id,
            'status' => Delivery::IN_PROGRESS,
            'assigned_at' => now()->subMinute(),
            'started_at' => now(),
        ]);
        DeliveryStatusHistory::on('race')->insert([
            ['delivery_id' => $delivery->id, 'actor_id' => $admin->id, 'from_status' => null, 'status' => Delivery::ASSIGNED, 'metadata' => null, 'notes' => null, 'created_at' => now()->subMinute(), 'updated_at' => now()->subMinute()],
            ['delivery_id' => $delivery->id, 'actor_id' => $driver->id, 'from_status' => Delivery::ASSIGNED, 'status' => Delivery::IN_PROGRESS, 'metadata' => null, 'notes' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->startRaceServers('payment');
        $driverToken = $this->raceLogin($driver->email);
        $outletToken = $this->raceLogin($outletUser->email);
        $responses = $this->runConcurrentHttpRequests([
            [
                'token' => $driverToken,
                'path' => "/api/deliveries/{$delivery->id}/status",
                'method' => 'PATCH',
                'body' => [
                    'status' => 'delivered',
                    'recipient_name' => 'Outlet manager',
                    'proof_of_delivery_url' => 'https://example.com/race-proof.jpg',
                ],
            ],
            [
                'token' => $outletToken,
                'path' => '/api/payments',
                'method' => 'POST',
                'body' => [
                    'order_id' => $order->id,
                    'amount' => 100000,
                    'payment_method' => 'cash',
                    'idempotency_key' => 'delivery-race-payment',
                ],
            ],
        ]);

        $this->assertSame(200, $responses[0]['status'], json_encode($responses[0]));
        $this->assertContains($responses[1]['status'], [200, 422], json_encode($responses[1]));
        $finalOrder = Order::on('race')->findOrFail($order->id);
        $history = OrderStatusHistory::on('race')->where('order_id', $order->id)->orderBy('id')->get();
        $this->assertSame($finalOrder->status, $history->last()->status);
        $this->assertSame(1, $history->where('status', 'Delivered')->count());
        if ($responses[1]['status'] === 422) {
            $this->assertSame('Delivered', $finalOrder->status);
        } else {
            $this->assertSame('Paid', $finalOrder->status);
            $this->assertSame(1, $history->where('status', 'Paid')->count());
        }

        $tracking = $this->publicHttpRequest('GET', $this->raceUrl(0)."/api/orders/{$order->id}", [], $outletToken);
        $this->assertSame(200, $tracking['status'], json_encode($tracking));
        $this->assertSame($finalOrder->status, $tracking['json']['data']['status']);
        $this->assertSame(1, count(array_filter($tracking['json']['data']['status_history'], fn (array $entry): bool => $entry['status'] === 'Delivered')));
    }

    private function prepareRaceDatabase(): void
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
        $this->raceDatabase = tempnam($directory, 'delivery-race-');
        if ($this->raceDatabase === false) {
            throw new \RuntimeException('Unable to create the race database file.');
        }
        config(['database.connections.race' => array_merge(config('database.connections.sqlite'), ['database' => $this->raceDatabase])]);
        \DB::purge('race');
        $this->artisan('migrate:fresh', ['--database' => 'race', '--force' => true]);
    }

    private function createRaceUser(string $email, string $role): User
    {
        $user = User::factory()->state(['role' => $role])->make([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);
        $user->setConnection('race');
        $user->save();
        return $user;
    }

    private function startRaceServers(string $section): void
    {
        $this->raceBarrierDirectory = storage_path('framework/testing/delivery-barrier-'.bin2hex(random_bytes(8)));
        mkdir($this->raceBarrierDirectory, 0777, true);
        $serverRouter = base_path('tests/Support/http_server.php');
        for ($index = 0; $index < 2; $index++) {
            $participant = $index === 0 ? 'A' : 'B';
            $port = $this->findFreePort();
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
            $environment['MAIL_MAILER'] = 'array';
            $environment['TELESCOPE_ENABLED'] = 'false';
            $environment['ORDER_CONCURRENCY_BARRIER_DIR'] = $this->raceBarrierDirectory;
            $environment['ORDER_CONCURRENCY_BARRIER_NAME'] = $section;
            $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT'] = $participant;
            $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, $serverRouter], [
                0 => ['pipe', 'r'], 1 => ['file', $log, 'ab'], 2 => ['file', $log, 'ab'],
            ], $pipes, base_path(), $environment);
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start the Laravel race server.');
            }
            fclose($pipes[0]);
            $this->raceServerProcesses[] = ['process' => $process, 'port' => $port, 'log' => $log];
            $this->waitForRaceServer($port, $log);
        }
    }

    private function waitForRaceServer(int $port, string $log): void
    {
        $context = stream_context_create(['http' => ['timeout' => 0.25, 'ignore_errors' => true, 'header' => "Accept: application/json\r\n"]]);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (@file_get_contents("http://127.0.0.1:{$port}/api/health", false, $context) !== false) {
                return;
            }
            usleep(50000);
        }
        throw new \RuntimeException('Race server did not start: '.(is_file($log) ? file_get_contents($log) : ''));
    }

    private function raceLogin(string $email): string
    {
        $response = $this->publicHttpRequest('POST', $this->raceUrl(0).'/api/auth/login', ['email' => $email, 'password' => 'password']);
        if (($response['status'] ?? 0) !== 200 || ! isset($response['json']['data']['token'])) {
            throw new \RuntimeException('Race server login failed: '.json_encode($response));
        }
        return $response['json']['data']['token'];
    }

    /** @param array<int, array{token: string, path: string, method: string, body: array<string, mixed>}> $requests */
    private function runConcurrentHttpRequests(array $requests): array
    {
        $worker = base_path('tests/Support/http_request.php');
        $processes = [];
        $files = [];
        foreach ($requests as $index => $request) {
            $input = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'request-'.$index.'.json';
            $output = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'response-'.$index.'.json';
            file_put_contents($input, json_encode([
                'method' => $request['method'], 'url' => $this->raceUrl($index).$request['path'],
                'headers' => ['Authorization' => 'Bearer '.$request['token']],
                'body' => json_encode($request['body'], JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR));
            $process = proc_open([PHP_BINARY, $worker, $input, $output], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start an HTTP worker.');
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
            if (! is_file($output)) {
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
    private function publicHttpRequest(string $method, string $url, array $body, ?string $token = null): array
    {
        $headers = "Accept: application/json\r\nContent-Type: application/json\r\n";
        if ($token) {
            $headers .= 'Authorization: Bearer '.$token."\r\n";
        }
        $context = stream_context_create(['http' => [
            'method' => $method, 'header' => $headers, 'content' => json_encode($body, JSON_THROW_ON_ERROR),
            'ignore_errors' => true, 'timeout' => 30,
        ]]);
        $responseBody = file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }
        return ['status' => $status, 'body' => $responseBody === false ? '' : $responseBody, 'json' => json_decode($responseBody ?: '', true)];
    }

    private function raceUrl(int $index): string
    {
        return 'http://127.0.0.1:'.$this->raceServerProcesses[$index]['port'];
    }

    private function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new \RuntimeException("Unable to reserve a local port: {$error}");
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
            \DB::purge('race');
            @unlink($this->raceDatabase);
            $this->raceDatabase = null;
        }
    }

    private function loginAs(User $user): string
    {
        return $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->json('data.token');
    }
}
