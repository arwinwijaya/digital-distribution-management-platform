<?php

namespace App\Services;

use App\Contracts\WhatsAppClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppHttpClient implements WhatsAppClient
{
    public function sendText(string $to, string $text): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text],
        ]);
    }

    public function sendTextWithIdempotency(string $to, string $text, string $idempotencyKey): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text],
        ], $idempotencyKey);
    }

    public function sendCatalog(string $to, array $catalog): array
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $this->catalogText($catalog)],
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function post(array $payload, ?string $idempotencyKey = null): array
    {
        $token = config('whatsapp.access_token');
        $phoneNumberId = config('whatsapp.phone_number_id');
        if (! $token || ! $phoneNumberId) {
            throw new RuntimeException('WhatsApp provider credentials are not configured.');
        }

        $request = Http::withToken($token);
        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }
        $response = $request
            ->post(rtrim((string) config('whatsapp.api_url'), '/').'/'.$phoneNumberId.'/messages', $payload)
            ->throw();

        return $response->json() ?? [];
    }

    /** @param array<int, array<string, mixed>> $catalog */
    private function catalogText(array $catalog): string
    {
        return collect($catalog)->map(fn (array $item) => sprintf(
            '%s (%s) - %s',
            $item['name'],
            $item['sku'],
            number_format((float) $item['price'], 2, '.', '')
        ))->implode("\n");
    }
}
