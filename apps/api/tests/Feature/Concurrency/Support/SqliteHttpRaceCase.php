<?php

namespace Tests\Feature\Concurrency\Support;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Shared file-backed SQLite HTTP race infrastructure.
 *
 * The three original copies (OrderTest, DeliveryConcurrencyTest,
 * PrePilotConcurrencyCompatibilityTest) duplicated this harness verbatim. It
 * boots two real Laravel HTTP workers against a shared file-backed SQLite
 * database and releases them together through a barrier file, so the suite
 * exercises concurrent request handling without requiring PostgreSQL.
 *
 * NOTE: SQLite serializes the eventual write. Tests using this trait assert the
 * retry/read-back result, not true simultaneous row locking. PostgreSQL row-lock
 * behaviour is covered separately by the pgsql race harnesses.
 */
trait SqliteHttpRaceCase
{
    protected ?string $raceDatabase = null;
    protected ?string $raceBarrierDirectory = null;

    /** @var array<int, array{process: resource, port: int, log: string}> */
    protected array $raceServerProcesses = [];

    protected function prepareRaceDatabase(string $prefix = 'race'): void
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

        $this->raceDatabase = tempnam($directory, $prefix.'-');
        if ($this->raceDatabase === false) {
            throw new RuntimeException('Unable to create the race database file.');
        }

        config(['database.connections.race' => array_merge(
            config('database.connections.sqlite'),
            ['database' => $this->raceDatabase],
        )]);
        DB::purge('race');
        $this->artisan('migrate:fresh', ['--database' => 'race', '--force' => true]);
        // Race HTTP workers resolve authorization through the `rbac` middleware,
        // which reads `role_menu_access`. Seed the default matrix on the race
        // connection so those workers do not 403 every protected route.
        $this->artisan('db:seed', [
            '--class' => \Database\Seeders\RbacMatrixSeeder::class,
            '--database' => 'race',
            '--force' => true,
        ]);
    }

    protected function createRaceUser(string $email, string $role = 'outlet', string $password = 'password123'): User
    {
        $user = User::factory()->make([
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
        ]);
        $user->setConnection('race');
        $user->save();

        return $user;
    }

    /** @return array{0: User, 1: Outlet} */
    protected function createRaceOutlet(string $email = 'race-outlet@ddp.test'): array
    {
        $user = $this->createRaceUser($email);
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

    protected function startRaceServers(string $section, string $barrierPrefix = 'race-barrier'): void
    {
        $this->raceBarrierDirectory = storage_path('framework/testing/'.$barrierPrefix.'-'.bin2hex(random_bytes(8)));
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
            $environment['ORDER_CONCURRENCY_BARRIER_ENABLED'] = 'true';
            $environment['ORDER_CONCURRENCY_BARRIER_DIR'] = $this->raceBarrierDirectory;
            $environment['ORDER_CONCURRENCY_BARRIER_NAME'] = $section;
            $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT'] = $participant;

            $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, $serverRouter], [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'ab'],
                2 => ['file', $log, 'ab'],
            ], $pipes, base_path(), $environment);
            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start the Laravel race server.');
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
        throw new RuntimeException("Race server did not start: {$details}");
    }

    protected function raceLogin(string $email, string $password = 'password123'): string
    {
        $response = $this->publicHttpRequest(
            'POST',
            $this->raceUrl(0).'/api/auth/login',
            ['email' => $email, 'password' => $password],
        );
        if (($response['status'] ?? 0) !== 200 || ! isset($response['json']['data']['token'])) {
            throw new RuntimeException('Race server login failed: '.json_encode($response));
        }

        return $response['json']['data']['token'];
    }

    /**
     * @param  array<int, array{token: string, body: array<string, mixed>, path?: string, method?: string}>  $requests
     */
    protected function runConcurrentHttpRequests(array $requests, ?string $defaultPath = null): array
    {
        $worker = base_path('tests/Support/http_request.php');
        $processes = [];
        $files = [];
        foreach ($requests as $index => $request) {
            $input = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'request-'.$index.'.json';
            $output = $this->raceBarrierDirectory.DIRECTORY_SEPARATOR.'response-'.$index.'.json';
            $path = $request['path'] ?? $defaultPath;
            if ($path === null) {
                throw new RuntimeException("Concurrent request {$index} is missing a path.");
            }
            file_put_contents($input, json_encode([
                'method' => $request['method'] ?? 'POST',
                'url' => $this->raceUrl($index).$path,
                'headers' => ['Authorization' => 'Bearer '.$request['token']],
                'body' => json_encode($request['body'] ?? [], JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR));

            $process = proc_open([PHP_BINARY, $worker, $input, $output], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, base_path());
            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start an HTTP worker.');
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
                throw new RuntimeException("HTTP worker {$index} produced no response.");
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
    protected function publicHttpRequest(string $method, string $url, array $body, ?string $token = null): array
    {
        $headers = "Accept: application/json\r\nContent-Type: application/json\r\n";
        if ($token !== null) {
            $headers .= 'Authorization: Bearer '.$token."\r\n";
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
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
            throw new RuntimeException("Unable to reserve a local port: {$error}");
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
