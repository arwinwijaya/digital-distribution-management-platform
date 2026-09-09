<?php

namespace Tests\Performance;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase as ApiTestCase;

/**
 * Real HTTP-boundary benchmark. It uses independent PHP workers and curl_multi
 * rather than Laravel's in-process test client, so the timing includes routing,
 * authentication, SQL, serialization, and network transport.
 *
 * Set PERFORMANCE_BASE_URL to benchmark an already deployed environment. An
 * opt-in PERFORMANCE_LOCAL=true mode creates a temporary migrated SQLite
 * database and launches local HTTP workers for development diagnostics.
 */
class LoadTest extends ApiTestCase
{
    private const CONCURRENT_USERS = 100;

    private const RESPONSE_LIMIT_SECONDS = 2.0;

    // The PHP built-in server is single-request-per-process on Windows. One
    // worker per concurrent request prevents an artificial queue from turning
    // client concurrency into sequential timing.
    private const WORKERS = 100;

    /** @var array<int, array{process: resource, port: int}> */
    private array $servers = [];

    private ?string $databasePath = null;

    public function test_performance_reaches_one_hundred_concurrent_authenticated_reads_under_two_seconds(): void
    {
        if (! extension_loaded('curl')) {
            $this->markTestSkipped('The real HTTP benchmark requires the PHP curl extension.');
        }

        $baseUrl = getenv('PERFORMANCE_BASE_URL') ? rtrim((string) getenv('PERFORMANCE_BASE_URL'), '/') : null;
        if ($baseUrl === null && filter_var(getenv('PERFORMANCE_LOCAL') ?: 'false', FILTER_VALIDATE_BOOLEAN)) {
            $this->prepareLocalScaleEnvironment();
            $baseUrl = $this->startLocalWorkers();
        }
        if ($baseUrl === null) {
            $this->markTestSkipped('A true 100-user benchmark needs PERFORMANCE_BASE_URL, PERFORMANCE_ADMIN_TOKEN, and PERFORMANCE_OUTLET_TOKENS; set PERFORMANCE_LOCAL=true only when local workers can handle 100 concurrent sockets.');
        }

        try {
            $tokens = $this->tokensForBenchmark();
            $requests = [];
            for ($index = 0; $index < self::CONCURRENT_USERS; $index++) {
                $isAdminRead = $index % 4 >= 2;
                $path = match ($index % 4) {
                    0 => '/api/marketplace/products?per_page=25',
                    1 => '/api/marketplace/suppliers?per_page=25',
                    2 => '/api/admin/orders?limit=25',
                    default => '/api/analytics/dashboard',
                };
                $requests[] = [
                    'url' => $baseUrl.$path,
                    'token' => $isAdminRead ? $tokens['admin'] : $tokens['outlets'][$index % count($tokens['outlets'])],
                    'path' => strtok($path, '?'),
                ];
            }

            $started = microtime(true);
            $results = $this->runConcurrentRequests($requests, $baseUrl);
            $wallClock = microtime(true) - $started;
            $durations = array_map(static fn (array $result): float => $result['seconds'], $results);
            sort($durations);
            $p50 = $durations[(int) floor((count($durations) - 1) * 0.50)];
            $p95 = $durations[(int) floor((count($durations) - 1) * 0.95)];
            $max = max($durations);
            $failures = array_values(array_filter($results, static fn (array $result): bool => $result['status'] !== 200 || ! $result['json']));

            $evidence = [
                'concurrency' => self::CONCURRENT_USERS,
                'workers' => self::WORKERS,
                'requests' => count($results),
                'wall_clock_seconds' => round($wallClock, 4),
                'p50_seconds' => round($p50, 4),
                'p95_seconds' => round($p95, 4),
                'max_seconds' => round($max, 4),
                'threshold_seconds' => self::RESPONSE_LIMIT_SECONDS,
                'threshold_passed' => $p95 < self::RESPONSE_LIMIT_SECONDS && $max < self::RESPONSE_LIMIT_SECONDS,
                'status_failures' => count($failures),
                'by_path' => $this->summarizePaths($results),
                'base_url_mode' => getenv('PERFORMANCE_BASE_URL') ? 'external' : 'local-temporary-fixture',
            ];
            fwrite(STDOUT, "\nT9 PERFORMANCE EVIDENCE ".json_encode($evidence, JSON_UNESCAPED_SLASHES)."\n");

            $this->assertSame([], $failures, 'All benchmark requests must return HTTP 200.');
            $this->assertLessThan(self::RESPONSE_LIMIT_SECONDS, $p95, 'p95 response time exceeded the 2 second SLO.');
            $this->assertLessThan(self::RESPONSE_LIMIT_SECONDS, $max, 'Maximum response time exceeded the 2 second SLO.');
        } finally {
            $this->stopServers();
        }
    }

    protected function tearDown(): void
    {
        $this->stopServers();
        if ($this->databasePath && is_file($this->databasePath)) {
            @unlink($this->databasePath);
        }
        parent::tearDown();
    }

    private function prepareLocalScaleEnvironment(): void
    {
        $this->databasePath = tempnam(storage_path('framework/testing'), 't9-scale-');
        if ($this->databasePath === false) {
            throw new \RuntimeException('Unable to allocate a temporary benchmark database.');
        }
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        Artisan::call('migrate:fresh', ['--database' => 'sqlite', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ScaleFixtureSeeder', '--force' => true]);
        User::factory()->admin()->create([
            'name' => 'T9 Benchmark Admin',
            'email' => 't9-benchmark-admin@example.test',
        ]);
    }

    /** @return array{admin: string, outlets: array<int, string>} */
    private function tokensForBenchmark(): array
    {
        if (getenv('PERFORMANCE_BASE_URL')) {
            $outletTokens = array_values(array_filter(array_map('trim', explode(',', (string) getenv('PERFORMANCE_OUTLET_TOKENS')))));
            if (trim((string) getenv('PERFORMANCE_ADMIN_TOKEN')) === '' || count($outletTokens) < self::CONCURRENT_USERS) {
                $this->markTestSkipped('External benchmark requires one admin token and 100 outlet tokens in environment variables.');
            }

            return ['admin' => (string) getenv('PERFORMANCE_ADMIN_TOKEN'), 'outlets' => $outletTokens];
        }

        $admin = User::query()->where('email', 't9-benchmark-admin@example.test')->firstOrFail();
        $outlets = User::query()->where('role', 'outlet')->limit(self::CONCURRENT_USERS)->get();

        return [
            'admin' => auth('api')->login($admin),
            'outlets' => $outlets->map(fn (User $user): string => auth('api')->login($user))->all(),
        ];
    }

    private function startLocalWorkers(): string
    {
        $router = base_path('tests/Support/http_server.php');
        $ports = [];
        for ($index = 0; $index < self::WORKERS; $index++) {
            $port = $this->findFreePort();
            $environment = getenv();
            $environment['APP_ENV'] = 'testing';
            $environment['APP_KEY'] = (string) config('app.key');
            $environment['JWT_SECRET'] = (string) config('jwt.secret');
            $environment['DB_CONNECTION'] = 'sqlite';
            $environment['DB_DATABASE'] = $this->databasePath;
            $environment['CACHE_STORE'] = 'array';
            $environment['SESSION_DRIVER'] = 'array';
            $environment['QUEUE_CONNECTION'] = 'sync';
            $process = proc_open(
                [PHP_BINARY, '-S', '127.0.0.1:'.$port, $router],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                $environment
            );
            if (! is_resource($process)) {
                throw new \RuntimeException('Unable to start T9 HTTP worker.');
            }
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->servers[] = ['process' => $process, 'port' => $port];
            $ports[] = $port;
            $this->waitForServer($port);
        }

        return 'http://127.0.0.1:'.$ports[0];
    }

    /** @param array<int, array{url: string, token: string, path: string}> $requests */
    private function runConcurrentRequests(array $requests, string $baseUrl): array
    {
        $multi = curl_multi_init();
        $handles = [];
        foreach ($requests as $index => $request) {
            $worker = $this->servers === []
                ? $baseUrl
                : 'http://127.0.0.1:'.$this->servers[$index % count($this->servers)]['port'];
            $url = $this->servers === [] ? $request['url'] : $worker.parse_url($request['url'], PHP_URL_PATH).(parse_url($request['url'], PHP_URL_QUERY) ? '?'.parse_url($request['url'], PHP_URL_QUERY) : '');
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Authorization: Bearer '.$request['token']],
                CURLOPT_CONNECTTIMEOUT_MS => 1000,
                CURLOPT_TIMEOUT_MS => 10000,
                CURLOPT_PRIVATE => json_encode(['index' => $index, 'path' => $request['path']], JSON_THROW_ON_ERROR),
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[] = $handle;
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running > 0 && $status === CURLM_OK) {
                curl_multi_select($multi, 0.1);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $results = [];
        foreach ($handles as $handle) {
            $private = json_decode((string) curl_getinfo($handle, CURLINFO_PRIVATE), true, 512, JSON_THROW_ON_ERROR);
            $results[$private['index']] = [
                'path' => $private['path'],
                'status' => (int) curl_getinfo($handle, CURLINFO_HTTP_CODE),
                'seconds' => (float) curl_getinfo($handle, CURLINFO_TOTAL_TIME),
                'json' => json_decode((string) curl_multi_getcontent($handle), true) !== null,
                'error' => curl_error($handle),
            ];
            curl_multi_remove_handle($multi, $handle);
            curl_close($handle);
        }
        curl_multi_close($multi);
        ksort($results);

        return array_values($results);
    }

    /** @param array<int, array{path: string, status: int, seconds: float, json: bool, error: string}> $results */
    private function summarizePaths(array $results): array
    {
        $durations = [];
        $summary = [];
        foreach ($results as $result) {
            $path = $result['path'];
            $durations[$path][] = $result['seconds'];
            $summary[$path] ??= ['requests' => 0, 'p50_seconds' => 0.0, 'p95_seconds' => 0.0, 'max_seconds' => 0.0, 'statuses' => []];
            $summary[$path]['requests']++;
            $summary[$path]['statuses'][(string) $result['status']] = ($summary[$path]['statuses'][(string) $result['status']] ?? 0) + 1;
        }
        foreach ($summary as $path => &$metrics) {
            sort($durations[$path]);
            $last = count($durations[$path]) - 1;
            $metrics['p50_seconds'] = round($durations[$path][(int) floor($last * 0.50)], 4);
            $metrics['p95_seconds'] = round($durations[$path][(int) floor($last * 0.95)], 4);
            $metrics['max_seconds'] = round(max($durations[$path]), 4);
        }

        return $summary;
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
        throw new \RuntimeException('T9 HTTP worker did not start.');
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
    }
}
