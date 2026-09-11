<?php

namespace Tests\Feature\Support;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

abstract class PostgresConcurrencyFeatureCase extends TestCase
{
    protected ?string $barrierDirectory = null;

    /** @var array<int, array{process: resource, port: int}> */
    protected array $servers = [];

    protected ?Outlet $outlet = null;
    protected ?Product $product = null;
    protected ?User $admin = null;
    protected string $prefix = '';
    protected ?string $orderIdentity = null;
    /** @var resource|null */
    protected $providerServer = null;
    protected ?int $providerPort = null;
    protected ?string $providerCallFile = null;
    /** @var array<int, resource> */
    protected array $commandProcesses = [];

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

        if (! DB::getSchemaBuilder()->hasTable('whatsapp_messages')
            || ! DB::getSchemaBuilder()->hasTable('invoice_reminders')) {
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
        $this->admin = User::factory()->admin()->create(['email' => $this->prefix.'@example.test']);
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
                DB::table('order_status_history')->whereIn('order_id', $orderIds)->delete();
                DB::table('orders')->whereIn('id', $orderIds)->delete();
            }
            $this->admin?->delete();
            $this->product?->delete();
            $this->outlet->delete();
        }
        parent::tearDown();
    }

    /** @param array<int, array{headers: array<string, string>, body: string}> $requests */
    protected function runConcurrent(array $requests, string $path): array
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
            $responses[$index] = json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
            @unlink($input);
            @unlink($output);
        }

        return $responses;
    }

    protected function startServers(string $sections, bool $whatsappBarrier = true): void
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
                unset($environment['ORDER_CONCURRENCY_BARRIER_DIR'], $environment['ORDER_CONCURRENCY_BARRIER_NAME'], $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT']);
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

    protected function startProviderServer(): void
    {
        $this->barrierDirectory = storage_path('framework/testing/'.$this->prefix);
        mkdir($this->barrierDirectory, 0777, true);
        $this->providerCallFile = $this->barrierDirectory.DIRECTORY_SEPARATOR.'provider-calls.log';
        file_put_contents($this->providerCallFile, '');
        $this->providerPort = $this->findFreePort();
        $environment = getenv();
        $environment['WHATSAPP_PROVIDER_CALL_FILE'] = $this->providerCallFile;
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.$this->providerPort, base_path('tests/Support/whatsapp_provider_server.php')],
            [0 => ['pipe', 'r'], 1 => ['file', $this->barrierDirectory.'/provider.log', 'ab'], 2 => ['file', $this->barrierDirectory.'/provider.log', 'ab']],
            $pipes,
            base_path(),
            $environment,
        );
        if (! is_resource($process)) {
            throw new \RuntimeException('Unable to start the fake WhatsApp provider.');
        }
        fclose($pipes[0]);
        $this->providerServer = $process;
        $this->waitForServer($this->providerPort);
    }

    protected function startCommandWorkers(): void
    {
        if ($this->barrierDirectory === null || $this->providerPort === null) {
            throw new \LogicException('Provider and barrier must be started before workers.');
        }

        for ($index = 0; $index < 2; $index++) {
            $participant = $index === 0 ? 'A' : 'B';
            $output = $this->barrierDirectory.DIRECTORY_SEPARATOR.'command-'.$participant.'.log';
            $environment = getenv();
            $environment['APP_ENV'] = 'testing';
            $environment['APP_KEY'] = (string) config('app.key');
            $environment['DB_CONNECTION'] = 'pgsql';
            foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
                $environment['DB_'.strtoupper($key)] = (string) config('database.connections.pgsql.'.$key);
            }
            $environment['DB_URL'] = '';
            $environment['WHATSAPP_ENABLED'] = 'true';
            $environment['WHATSAPP_API_URL'] = 'http://127.0.0.1:'.$this->providerPort;
            $environment['WHATSAPP_ACCESS_TOKEN'] = 'test-provider-token';
            $environment['WHATSAPP_PHONE_NUMBER_ID'] = 'test-phone-number';
            $environment['WHATSAPP_CONCURRENCY_BARRIER_ENABLED'] = 'true';
            $environment['ORDER_CONCURRENCY_BARRIER_DIR'] = $this->barrierDirectory;
            $environment['ORDER_CONCURRENCY_BARRIER_NAME'] = $this->prefix;
            $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT'] = $participant;
            $environment['ORDER_CONCURRENCY_BARRIER_SECTIONS'] = 'invoice-reminder';
            $process = proc_open(
                [PHP_BINARY, base_path('artisan'), 'invoices:reminders', '--no-ansi'],
                [0 => ['pipe', 'r'], 1 => ['file', $output, 'ab'], 2 => ['file', $output, 'ab']],
                $pipes,
                base_path(),
                $environment,
            );
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start a PostgreSQL reminder worker.');
            }
            fclose($pipes[0]);
            $this->commandProcesses[] = $process;
        }
    }

    protected function waitForServer(int $port): void
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

    protected function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new \RuntimeException('Unable to reserve a local port: '.$error);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr(strrchr($name, ':'), 1);
    }

    protected function stopServers(): void
    {
        foreach ($this->commandProcesses as $process) {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
        }
        $this->commandProcesses = [];
        if (is_resource($this->providerServer)) {
            if (proc_get_status($this->providerServer)['running']) {
                proc_terminate($this->providerServer);
            }
            proc_close($this->providerServer);
        }
        $this->providerServer = null;
        $this->providerPort = null;
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
