<?php

namespace Tests\Support;

use PDO;
use Throwable;

/**
 * Reachability probe for the opt-in PostgreSQL concurrency ("race") fixtures.
 *
 * The invoice and payment race harnesses create a throwaway PostgreSQL database
 * over a raw PDO connection. When the suite is run from a host that cannot
 * resolve the Docker-only `db` hostname, that connection throws. Because the
 * harnesses are also closed from tearDown(), the same throw would abort
 * tearDown() before RefreshDatabase rolls back, leaking the transaction and
 * cascading "There is already an active transaction" into every later test.
 *
 * Callers therefore skip gracefully when this probe reports the database is
 * unreachable, instead of letting the harness throw mid-test.
 *
 * Deliberately does NOT gate on config('database.default') === 'pgsql': the
 * phpunit environment pins the default connection to sqlite, so that check
 * would make this always report unavailable and silently remove coverage.
 */
final class PostgresRaceProbe
{
    public static function isAvailable(): bool
    {
        if (! extension_loaded('pdo_pgsql')) {
            return false;
        }

        /** @var array<string, mixed> $connection */
        $connection = config('database.connections.pgsql');

        if (! is_array($connection) || empty($connection['host'])) {
            return false;
        }

        $host = (string) $connection['host'];
        $port = (string) ($connection['port'] ?? 5432);
        $database = (string) ($connection['database'] ?? '');

        // Prefer the configured database; fall back to the conventional
        // maintenance database when none is configured.
        $candidates = array_values(array_unique(array_filter([$database, 'postgres'])));

        foreach ($candidates as $name) {
            try {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;connect_timeout=2',
                    $host,
                    $port,
                    $name,
                );

                new PDO(
                    $dsn,
                    (string) ($connection['username'] ?? ''),
                    (string) ($connection['password'] ?? ''),
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
                );

                return true;
            } catch (Throwable) {
                // Try the next candidate database before giving up.
            }
        }

        return false;
    }
}
