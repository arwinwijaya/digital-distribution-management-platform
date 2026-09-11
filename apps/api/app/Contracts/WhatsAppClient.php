<?php

namespace App\Contracts;

interface WhatsAppClient
{
    /** @return array<string, mixed> */
    public function sendText(string $to, string $text): array;

    /**
     * Every provider text send that participates in retry/idempotency must use
     * the same explicit provider identity on every attempt.
     *
     * @return array<string, mixed>
     */
    public function sendTextWithIdempotency(string $to, string $text, string $idempotencyKey): array;

    /** @param array<int, array<string, mixed>> $catalog */
    public function sendCatalog(string $to, array $catalog): array;
}
