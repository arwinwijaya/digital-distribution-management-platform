<?php

namespace Tests\Unit;

use ReflectionProperty;
use Tests\Support\InvoiceConcurrencyHarness;
use Tests\Support\PaymentConcurrencyHarness;
use Tests\TestCase;
use Throwable;

/**
 * Regression lock for fix C in note.md: exception-safe close() on the
 * PostgreSQL race harnesses.
 *
 * close() runs from the test's tearDown(). If it throws, parent::tearDown()
 * never runs, RefreshDatabase never rolls back the sqlite :memory: transaction,
 * and every later test in the same process fails with "There is already an
 * active transaction". These tests assert close() can never throw.
 */
class ConcurrencyHarnessTeardownTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string}>
     */
    public static function harnessProvider(): array
    {
        return [
            'invoice harness' => [InvoiceConcurrencyHarness::class],
            'payment harness' => [PaymentConcurrencyHarness::class],
        ];
    }

    /**
     * @param  class-string  $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('harnessProvider')]
    public function test_close_on_never_prepared_harness_does_not_throw(string $class): void
    {
        $harness = new $class();

        $harness->close();

        $this->assertNull($this->readPrivate($harness, 'database'));
    }

    /**
     * @param  class-string  $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('harnessProvider')]
    public function test_close_is_idempotent_and_never_throws(string $class): void
    {
        $harness = new $class();

        $harness->close();
        $harness->close();

        $this->assertNull($this->readPrivate($harness, 'database'));
    }

    /**
     * @param  class-string  $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('harnessProvider')]
    public function test_close_swallows_connection_failure_and_clears_state(string $class): void
    {
        $harness = new $class();

        // Simulate a harness that prepared against an unreachable database and
        // then failed mid-teardown (the exact cascade trigger from note.md).
        $this->writePrivate($harness, 'database', 'ddp_unreachable_race');
        $this->writePrivate($harness, 'connection', [
            'host' => '127.0.0.1',
            'port' => '1',
            'database' => 'postgres',
            'username' => 'nobody',
            'password' => 'nothing',
        ]);

        try {
            $harness->close();
        } catch (Throwable $exception) {
            $this->fail('close() must never throw, but threw: '.$exception->getMessage());
        }

        $this->assertNull($this->readPrivate($harness, 'database'));
    }

    private function readPrivate(object $object, string $property): mixed
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    private function writePrivate(object $object, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($object, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($object, $value);
    }
}
