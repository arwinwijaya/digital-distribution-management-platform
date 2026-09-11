<?php

namespace Tests\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PDO;
use RuntimeException;

/** Isolated PostgreSQL HTTP race fixture for invoice approval integration tests. */
final class InvoiceConcurrencyHarness
{
    public const CONNECTION = 'invoice_race';

    private ?string $database = null;
    private ?string $barrierDirectory = null;
    /** @var array<int, array{process: resource, port: int}> */
    private array $servers = [];
    /** @var array<string, mixed> */
    private array $connection;

    public function prepare(): void
    {
        $this->connection = Config::get('database.connections.pgsql');
        $this->database = 'ddp_invoice_race_'.bin2hex(random_bytes(8));
        $admin = new PDO($this->dsn('postgres'), $this->connection['username'], $this->connection['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $admin->exec('CREATE DATABASE '.$this->quoteIdentifier($this->database));

        Config::set('database.connections.'.self::CONNECTION, array_merge(
            $this->connection,
            ['database' => $this->database],
        ));
        DB::purge(self::CONNECTION);
        Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);
    }

    public function createOrderFixture(): Order
    {
        $admin = $this->createUser('invoice-race-admin@example.test', 'admin');
        $outletUser = $this->createUser('invoice-race-outlet@example.test', 'outlet');
        $outlet = $this->createOutlet($outletUser);
        $product = $this->createProduct();
        $order = $this->createOrder($outlet, $product);
        $this->createHistory($order);
        $this->createItem($order, $product);

        return $order;
    }

    public function startServers(): void
    {
        $this->barrierDirectory = storage_path('framework/testing/invoice-barrier-'.bin2hex(random_bytes(8)));
        mkdir($this->barrierDirectory, 0777, true);
        $router = base_path('tests/Support/http_server.php');

        foreach (['A', 'B'] as $participant) {
            $port = $this->freePort();
            $log = $this->barrierDirectory.DIRECTORY_SEPARATOR.'server-'.$participant.'.log';
            $environment = getenv();
            $environment['APP_ENV'] = 'testing';
            $environment['APP_KEY'] = (string) config('app.key');
            $environment['DB_CONNECTION'] = 'pgsql';
            $environment['DB_HOST'] = (string) $this->connection['host'];
            $environment['DB_PORT'] = (string) $this->connection['port'];
            $environment['DB_DATABASE'] = $this->database;
            $environment['DB_USERNAME'] = (string) $this->connection['username'];
            $environment['DB_PASSWORD'] = (string) $this->connection['password'];
            $environment['CACHE_STORE'] = 'array';
            $environment['CACHE_DRIVER'] = 'array';
            $environment['SESSION_DRIVER'] = 'array';
            $environment['QUEUE_CONNECTION'] = 'sync';
            $environment['ORDER_CONCURRENCY_BARRIER_DIR'] = $this->barrierDirectory;
            $environment['ORDER_CONCURRENCY_BARRIER_NAME'] = 'approval';
            $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT'] = $participant;
            $environment['ORDER_CONCURRENCY_BARRIER_SECTIONS'] = 'approval';

            $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, $router], [
                0 => ['pipe', 'r'], 1 => ['file', $log, 'ab'], 2 => ['file', $log, 'ab'],
            ], $pipes, base_path(), $environment);
            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start invoice PostgreSQL race server.');
            }
            fclose($pipes[0]);
            $this->servers[] = ['process' => $process, 'port' => $port];
            $this->waitForServer($port, $log);
        }
    }

    /** @return array<int, array{status: int, body: string, json: array<string, mixed>|null}> */
    public function runConcurrentApprovals(int $orderId): array
    {
        $token = $this->login();
        $worker = base_path('tests/Support/http_request.php');
        $processes = [];
        $files = [];
        foreach ([0, 1] as $index) {
            $input = $this->barrierDirectory.DIRECTORY_SEPARATOR.'request-'.$index.'.json';
            $output = $this->barrierDirectory.DIRECTORY_SEPARATOR.'response-'.$index.'.json';
            file_put_contents($input, json_encode([
                'method' => 'PUT',
                'url' => $this->url($index).'/api/orders/'.$orderId.'/approve',
                'headers' => ['Authorization' => 'Bearer '.$token],
                'body' => '{}',
            ], JSON_THROW_ON_ERROR));
            $process = proc_open([PHP_BINARY, $worker, $input, $output], [
                0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes, base_path());
            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start invoice PostgreSQL HTTP worker.');
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
        foreach ($files as [$input, $output]) {
            if (! is_file($output)) {
                throw new RuntimeException('Invoice PostgreSQL HTTP worker produced no response.');
            }
            $raw = json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
            $raw['json'] = json_decode($raw['body'] ?? '', true);
            $responses[] = $raw;
            @unlink($input);
            @unlink($output);
        }

        return $responses;
    }

    public function close(): void
    {
        foreach ($this->servers as $server) {
            if (is_resource($server['process'])) {
                $status = proc_get_status($server['process']);
                if ($status['running']) {
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
        if ($this->database) {
            DB::purge(self::CONNECTION);
            $admin = new PDO($this->dsn('postgres'), $this->connection['username'], $this->connection['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $admin->exec('DROP DATABASE IF EXISTS '.$this->quoteIdentifier($this->database));
            $this->database = null;
        }
    }

    private function createUser(string $email, string $role): User
    {
        $user = User::factory()->make([
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
        ]);
        return $this->save($user);
    }

    private function createOutlet(User $user): Outlet
    {
        $outlet = Outlet::factory()->make(['user_id' => $user->id]);
        return $this->save($outlet);
    }

    private function createProduct(): Product
    {
        $product = Product::factory()->make([
            'price' => 100000,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        return $this->save($product);
    }

    private function createOrder(Outlet $outlet, Product $product): Order
    {
        $order = new Order([
            'order_id' => 'ORD-INVOICE-RACE',
            'outlet_id' => $outlet->id,
            'status' => 'New',
            'total_amount' => 100000,
            'commission_percentage' => 2.00,
            'idempotency_key' => 'invoice-race-order',
        ]);
        return $this->save($order);
    }

    private function createHistory(Order $order): void
    {
        $this->save(new OrderStatusHistory([
            'order_id' => $order->id,
            'status' => 'New',
            'notes' => 'Invoice race fixture',
        ]));
    }

    private function createItem(Order $order, Product $product): void
    {
        $this->save(new OrderItem([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'subtotal' => 100000,
        ]));
    }

    private function save(object $model): object
    {
        $model->setConnection(self::CONNECTION);
        $model->save();
        return $model;
    }

    private function login(): string
    {
        $response = $this->request('POST', $this->url(0).'/api/auth/login', [
            'email' => 'invoice-race-admin@example.test',
            'password' => 'password123',
        ]);
        if (($response['status'] ?? 0) !== 200 || ! isset($response['json']['data']['token'])) {
            throw new RuntimeException('Invoice PostgreSQL race login failed: '.json_encode($response));
        }
        return $response['json']['data']['token'];
    }

    /** @return array{status: int, body: string, json: array<string, mixed>|null} */
    private function request(string $method, string $url, array $body): array
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Accept: application/json\r\nContent-Type: application/json\r\n",
            'content' => json_encode($body, JSON_THROW_ON_ERROR),
            'ignore_errors' => true,
            'timeout' => 30,
        ]]);
        $body = file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }
        return ['status' => $status, 'body' => $body === false ? '' : $body, 'json' => json_decode($body ?: '', true)];
    }

    private function waitForServer(int $port, string $log): void
    {
        $context = stream_context_create(['http' => ['timeout' => 0.25, 'ignore_errors' => true]]);
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (@file_get_contents('http://127.0.0.1:'.$port.'/api/health', false, $context) !== false) {
                return;
            }
            usleep(50000);
        }
        throw new RuntimeException('Invoice PostgreSQL race server did not start: '.(is_file($log) ? file_get_contents($log) : ''));
    }

    private function url(int $index): string
    {
        return 'http://127.0.0.1:'.$this->servers[$index]['port'];
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException("Unable to reserve invoice PostgreSQL race port: {$error}");
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        return (int) substr(strrchr($name, ':'), 1);
    }

    private function dsn(string $database): string
    {
        return 'pgsql:host='.$this->connection['host'].';port='.$this->connection['port'].';dbname='.$database;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
