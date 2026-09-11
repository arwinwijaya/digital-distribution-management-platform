<?php

namespace Tests\Support;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PDO;
use RuntimeException;

/** Isolated PostgreSQL HTTP race fixture for payment integration tests. */
final class PaymentConcurrencyHarness
{
    public const CONNECTION = 'payment_race';

    private ?string $database = null;
    private ?PaymentConcurrencyHttp $http = null;

    /** @var array<string, mixed> */
    private array $connection = [];

    public function prepare(): void
    {
        if (! extension_loaded('pdo_pgsql')) {
            throw new RuntimeException('Payment PostgreSQL race requires the pdo_pgsql extension.');
        }

        $this->connection = Config::get('database.connections.pgsql');
        $this->database = 'ddp_payment_race_'.bin2hex(random_bytes(8));
        $admin = new PDO(
            $this->dsn('postgres'),
            $this->connection['username'],
            $this->connection['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $admin->exec('CREATE DATABASE '.$this->quoteIdentifier($this->database));

        Config::set('database.connections.'.self::CONNECTION, array_merge(
            $this->connection,
            ['database' => $this->database],
        ));
        DB::purge(self::CONNECTION);
        Artisan::call('migrate:fresh', ['--database' => self::CONNECTION, '--force' => true]);
        $this->http = new PaymentConcurrencyHttp($this->connection, $this->database);
    }

    public function createPaymentFixture(): Order
    {
        $admin = User::factory()->make([
            'email' => 'payment-race-admin@example.test',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
        $this->save($admin);

        $outletUser = User::factory()->make([
            'email' => 'payment-race-outlet@example.test',
            'password' => Hash::make('password123'),
            'role' => 'outlet',
        ]);
        $this->save($outletUser);
        $outlet = Outlet::factory()->make([
            'user_id' => $outletUser->id,
            'is_active' => true,
        ]);
        $this->save($outlet);

        $order = new Order([
            'order_id' => 'ORD-RACE-PAYMENT',
            'outlet_id' => $outlet->id,
            'status' => 'Delivered',
            'total_amount' => 100000,
            'paid_amount' => 0,
            'commission_percentage' => 2.00,
            'idempotency_key' => 'race-payment-order',
        ]);
        $this->save($order);
        $this->save(new OrderStatusHistory([
            'order_id' => $order->id,
            'status' => 'Delivered',
            'notes' => 'Delivered for payment race test',
        ]));
        $this->save(new Invoice([
            'order_id' => $order->id,
            'outlet_id' => $outlet->id,
            'invoice_number' => 'INV-RACE-PAYMENT',
            'issue_date' => now()->subDays(7)->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'total_amount' => 100000,
            'paid_amount' => 0,
            'balance_amount' => 100000,
            'status' => Invoice::UNPAID,
        ]));

        return $order;
    }

    public function startServers(): void
    {
        $this->http?->start();
    }

    /** @return array<int, array{status: int, body: string, json: array<string, mixed>|null}> */
    public function runConcurrentPayment(int $orderId, array $payload): array
    {
        if ($this->http === null) {
            throw new RuntimeException('Payment PostgreSQL race servers are not prepared.');
        }

        return $this->http->runConcurrentPayment($orderId, $payload);
    }

    public function close(): void
    {
        $this->http?->close();
        $this->http = null;

        if ($this->database === null) {
            return;
        }

        DB::purge(self::CONNECTION);
        $admin = new PDO(
            $this->dsn('postgres'),
            $this->connection['username'],
            $this->connection['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $admin->exec('DROP DATABASE IF EXISTS '.$this->quoteIdentifier($this->database));
        $this->database = null;
    }

    private function save(object $model): object
    {
        $model->setConnection(self::CONNECTION);
        $model->save();
        return $model;
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
