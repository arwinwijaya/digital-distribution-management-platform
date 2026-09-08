<?php

namespace App\Contracts;

interface WhatsAppClient
{
    /** @return array<string, mixed> */
    public function sendText(string $to, string $text): array;

    /** @param array<int, array<string, mixed>> $catalog */
    public function sendCatalog(string $to, array $catalog): array;
}
