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

/** Isolated PostgreSQL HTTP race fixture for invoice approval integration tests. */
final class InvoiceConcurrencyHarness
{
    public const CONNECTION = 'invoice_race';

    private ?string $database = null;
    private ?InvoiceConcurrencyHttp $http = null;
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
        $this->http = new InvoiceConcurrencyHttp($this->connection, $this->database);
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
        $this->http->start();
    }

    /** @return array<int, array{status: int, body: string, json: array<string, mixed>|null}> */
    public function runConcurrentApprovals(int $orderId): array
    {
        return $this->http->runConcurrentApprovals($orderId);
    }

    public function close(): void
    {
        $this->http?->close();
        $this->http = null;
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

    private function dsn(string $database): string
    {
        return 'pgsql:host='.$this->connection['host'].';port='.$this->connection['port'].';dbname='.$database;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
