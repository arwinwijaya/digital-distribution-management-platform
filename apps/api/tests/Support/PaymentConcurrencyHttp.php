<?php

namespace Tests\Support;

use RuntimeException;

/** Runs two independent PostgreSQL HTTP workers for payment races. */
final class PaymentConcurrencyHttp
{
    private ?string $barrierDirectory = null;

    /** @var array<int, array{process: resource, port: int}> */
    private array $servers = [];

    /** @param array<string, mixed> $connection */
    public function __construct(
        private readonly array $connection,
        private readonly string $database,
    ) {}

    public function start(): void
    {
        $this->barrierDirectory = storage_path('framework/testing/payment-barrier-'.bin2hex(random_bytes(8)));
        if (! mkdir($this->barrierDirectory, 0777, true) && ! is_dir($this->barrierDirectory)) {
            throw new RuntimeException('Unable to create payment PostgreSQL race directory.');
        }
        $router = base_path('tests/Support/http_server.php');

        foreach (['A', 'B'] as $participant) {
            $port = $this->freePort();
            $log = $this->barrierDirectory.DIRECTORY_SEPARATOR.'server-'.$participant.'.log';
            $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:'.$port, $router], [
                0 => ['pipe', 'r'],
                1 => ['file', $log, 'ab'],
                2 => ['file', $log, 'ab'],
            ], $pipes, base_path(), $this->environment($participant));
            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start payment PostgreSQL race server.');
            }
            fclose($pipes[0]);
            $this->servers[] = ['process' => $process, 'port' => $port];
            $this->waitForServer($port, $log);
        }
    }

    /** @return array<int, array{status: int, body: string, json: array<string, mixed>|null}> */
    public function runConcurrentPayment(int $orderId, array $payload): array
    {
        if ($this->barrierDirectory === null || count($this->servers) !== 2) {
            throw new RuntimeException('Payment PostgreSQL race servers are not running.');
        }

        $token = $this->login();
        $worker = base_path('tests/Support/http_request.php');
        $processes = [];
        $files = [];
        foreach ([0, 1] as $index) {
            $input = $this->barrierDirectory.DIRECTORY_SEPARATOR.'request-'.$index.'.json';
            $output = $this->barrierDirectory.DIRECTORY_SEPARATOR.'response-'.$index.'.json';
            file_put_contents($input, json_encode([
                'method' => 'POST',
                'url' => $this->url($index).'/api/payments',
                'headers' => ['Authorization' => 'Bearer '.$token],
                'body' => json_encode(['order_id' => $orderId, ...$payload], JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR));
            $process = proc_open([PHP_BINARY, $worker, $input, $output], [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes, base_path());
            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start payment PostgreSQL HTTP worker.');
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
                throw new RuntimeException("Payment PostgreSQL HTTP worker {$index} produced no response.");
            }
            $raw = json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
            $raw['json'] = json_decode($raw['body'] ?? '', true);
            $responses[$index] = $raw;
            unlink($input);
            unlink($output);
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
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->barrierDirectory);
        }
        $this->barrierDirectory = null;
    }

    /** @return array<string, string> */
    private function environment(string $participant): array
    {
        $environment = getenv();
        $environment['APP_ENV'] = 'testing';
        $environment['APP_KEY'] = (string) config('app.key');
        // Keep the worker database explicit: the required command must exercise
        // PostgreSQL, never inherit a SQLite test override.
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
        $environment['MAIL_MAILER'] = 'array';
        $environment['TELESCOPE_ENABLED'] = 'false';
        $environment['ORDER_CONCURRENCY_BARRIER_DIR'] = $this->barrierDirectory;
        $environment['ORDER_CONCURRENCY_BARRIER_NAME'] = 'payment';
        $environment['ORDER_CONCURRENCY_BARRIER_PARTICIPANT'] = $participant;
        $environment['ORDER_CONCURRENCY_BARRIER_SECTIONS'] = 'payment';

        return $environment;
    }

    private function login(): string
    {
        $response = $this->request('POST', $this->url(0).'/api/auth/login', [
            'email' => 'payment-race-admin@example.test',
            'password' => 'password123',
        ]);
        if (($response['status'] ?? 0) !== 200 || ! isset($response['json']['data']['token'])) {
            throw new RuntimeException('Payment PostgreSQL race login failed: '.json_encode($response));
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

        return [
            'status' => $status,
            'body' => $body === false ? '' : $body,
            'json' => json_decode($body ?: '', true),
        ];
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

        throw new RuntimeException('Payment PostgreSQL race server did not start: '.(is_file($log) ? file_get_contents($log) : ''));
    }

    private function url(int $index): string
    {
        return 'http://127.0.0.1:'.$this->servers[$index]['port'];
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException("Unable to reserve payment PostgreSQL race port: {$error}");
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr(strrchr($name, ':'), 1);
    }
}
