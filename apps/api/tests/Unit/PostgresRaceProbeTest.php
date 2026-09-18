<?php

namespace Tests\Unit;

use Tests\Support\PostgresRaceProbe;
use Tests\TestCase;

/**
 * Regression lock for fix A in note.md: PostgresRaceProbe::isAvailable().
 *
 * The probe exists so the opt-in PostgreSQL race harnesses skip gracefully on
 * hosts that cannot reach the Docker-only `db` hostname. It must never throw —
 * a throw inside a test would cascade into every later test in the process.
 */
class PostgresRaceProbeTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalPgsql;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var array<string, mixed> $pgsql */
        $pgsql = config('database.connections.pgsql');
        $this->originalPgsql = $pgsql;
    }

    protected function tearDown(): void
    {
        config(['database.connections.pgsql' => $this->originalPgsql]);

        parent::tearDown();
    }

    public function test_returns_false_when_pgsql_connection_has_no_host(): void
    {
        config(['database.connections.pgsql.host' => '']);

        $this->assertFalse(PostgresRaceProbe::isAvailable());
    }

    public function test_returns_false_when_pgsql_connection_is_not_an_array(): void
    {
        config(['database.connections.pgsql' => 'not-a-connection']);

        $this->assertFalse(PostgresRaceProbe::isAvailable());
    }

    public function test_returns_false_instead_of_throwing_when_database_is_unreachable(): void
    {
        // Port 1 is refused immediately; the probe must swallow the PDOException
        // and report unavailability rather than letting it bubble up.
        config([
            'database.connections.pgsql.host' => '127.0.0.1',
            'database.connections.pgsql.port' => '1',
            'database.connections.pgsql.database' => '',
        ]);

        $this->assertFalse(PostgresRaceProbe::isAvailable());
    }

    public function test_probe_does_not_depend_on_the_default_connection(): void
    {
        // The suite's default connection is sqlite (phpunit.xml.dist) or pgsql
        // (phpunit.pgsql.xml); the probe must evaluate the pgsql connection
        // itself instead of short-circuiting to false based on the default.
        config(['database.connections.pgsql.host' => '']);

        $this->assertContains(config('database.default'), ['sqlite', 'pgsql']);
        $this->assertFalse(PostgresRaceProbe::isAvailable());
    }
}
