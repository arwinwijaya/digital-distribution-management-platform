<?php

namespace App\Support;

/**
 * A deliberately opt-in barrier for exercising request races in feature tests.
 *
 * It is inert unless the application is running in testing mode and all barrier
 * configuration values are present. Each HTTP worker waits after entering its
 * transaction until the other worker reaches the same point, then both proceed.
 */
final class ConcurrencyTestBarrier
{
    /**
     * Pause both participants at a transaction-critical boundary.
     */
    public static function await(string $section): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $directory = config('orders.concurrency_barrier_dir');
        $name = config('orders.concurrency_barrier_name');
        $participant = config('orders.concurrency_barrier_participant');
        $sections = config('orders.concurrency_barrier_sections', []);

        if (is_array($sections) && $sections !== [] && ! in_array($section, $sections, true)) {
            return;
        }

        if (! is_string($directory) || $directory === ''
            || ! is_string($name) || $name === ''
            || ! is_string($participant) || $participant === '') {
            return;
        }

        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $prefix = $directory.DIRECTORY_SEPARATOR.$name.'-'.$section;
        $self = $prefix.'-'.$participant;
        $otherParticipant = $participant === 'A' ? 'B' : 'A';
        $other = $prefix.'-'.$otherParticipant;
        $release = $prefix.'-released';

        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create the concurrency barrier directory.');
        }

        file_put_contents($self, (string) getmypid(), LOCK_EX);

        $deadline = microtime(true) + 20.0;
        while (! file_exists($other)) {
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException("Timed out waiting for concurrency participant {$otherParticipant}.");
            }
            usleep(10000);
        }

        // Either participant may release the barrier after both have arrived.
        if (! file_exists($release)) {
            @file_put_contents($release, (string) microtime(true), LOCK_EX);
        }

        while (! file_exists($release)) {
            if (microtime(true) >= $deadline) {
                throw new \RuntimeException('Timed out waiting for the concurrency barrier release.');
            }
            usleep(10000);
        }
    }
}
