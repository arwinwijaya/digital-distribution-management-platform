<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\WhatsAppMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class WhatsAppService
{
    public function __construct(
        private readonly OrderCreationService $orderCreationService,
        private readonly WhatsAppPayloadParser $parser,
        private readonly WhatsAppSenderResolver $senderResolver,
        private readonly WhatsAppOutboundService $outbound,
    ) {}

    /** @param array<string, mixed> $payload @param array<string, string|null> $headers */
    public function handleWebhook(array $payload, string $rawBody, array $headers): array
    {
        if (! config('whatsapp.enabled')) {
            return ['http_status' => 202, 'status' => 'disabled', 'message' => 'WhatsApp integration is currently unavailable.'];
        }
        if (! $this->validSignature($rawBody, $headers)) {
            return ['http_status' => 401, 'status' => 'error', 'message' => 'Invalid or missing webhook signature.'];
        }

        $message = $this->parser->extractMessage($payload);
        $providerId = $this->parser->firstString($message, ['provider_message_id', 'message_id', 'event_id', 'id']);
        $phone = $this->parser->firstString($message, ['from', 'phone', 'phone_number', 'sender']);
        $body = $this->parser->extractBody($message);
        if (! $providerId || ! $phone || ! $body) {
            return ['http_status' => 422, 'status' => 'error', 'message' => 'Webhook must include a message id, sender phone, and text message.'];
        }

        return DB::transaction(function () use ($providerId, $phone, $body, $payload): array {
            $record = $this->lockInbound($providerId, $phone, $body, $payload);
            if (in_array($record->status, ['processed', 'rejected'], true)) {
                return [
                    'http_status' => 200,
                    'status' => 'duplicate',
                    'duplicate' => true,
                    'message' => 'Event was already processed.',
                    'data' => ['order_id' => $record->order_id],
                ];
            }

            $resolved = $this->senderResolver->resolve($phone);
            if ($resolved['ambiguous']) {
                $message = 'Sender matches multiple active outlets.';
                $record->update(['status' => 'rejected', 'error' => $message]);

                return ['http_status' => 422, 'status' => 'error', 'message' => $message];
            }
            $outlet = $resolved['outlet'];
            if (! $outlet) {
                $message = 'Sender is not mapped to an active outlet.';
                $record->update(['status' => 'rejected', 'error' => $message]);

                return ['http_status' => 422, 'status' => 'error', 'message' => $message];
            }

            try {
                $items = $this->parser->parseItems($body, $payload);
                $result = $this->orderCreationService->create(
                    ['items' => $items],
                    $outlet,
                    hash('sha256', 'whatsapp:'.$providerId),
                );
                $order = $result['order'];
                $record->update(['status' => 'processed', 'outlet_id' => $outlet->id, 'order_id' => $order->id, 'error' => null]);
                $order->load('items.product');

                return [
                    'http_status' => $result['created'] ? 201 : 200,
                    'status' => $result['created'] ? 'success' : 'duplicate',
                    'duplicate' => ! $result['created'],
                    'data' => $order,
                ];
            } catch (ValidationException $exception) {
                $error = $this->validationMessage($exception);
                $record->update(['status' => 'rejected', 'outlet_id' => $outlet->id, 'error' => $error]);

                return ['http_status' => 422, 'status' => 'error', 'message' => $error, 'errors' => $exception->errors()];
            } catch (Throwable $exception) {
                // The row remains failed only after the lock-protected transaction
                // commits, so the provider can retry it without concurrent orders.
                $record->update(['status' => 'failed', 'outlet_id' => $outlet->id, 'error' => $exception->getMessage()]);

                return ['http_status' => 503, 'status' => 'error', 'message' => 'The order could not be accepted. Please retry the message.'];
            }
        });
    }

    public function notifyConfirmedOrder(Order $order): ?WhatsAppMessage
    {
        return $this->outbound->notifyConfirmedOrder($order);
    }

    public function retryMessage(WhatsAppMessage $message): WhatsAppMessage
    {
        return $this->outbound->retryMessage($message);
    }

    /** @return array{message: WhatsAppMessage, status: string} */
    public function shareCatalog(Outlet $outlet): array
    {
        return $this->outbound->shareCatalog($outlet);
    }

    private function validSignature(string $rawBody, array $headers): bool
    {
        $secret = config('whatsapp.webhook_secret');
        $provided = $headers['x-hub-signature-256'] ?? $headers['x-whatsapp-signature'] ?? null;
        if (is_string($secret) && $secret !== '' && is_string($provided)) {
            $provided = preg_replace('/^sha256=/i', '', trim($provided));
            if (is_string($provided) && hash_equals(hash_hmac('sha256', $rawBody, $secret), $provided)) {
                return true;
            }
        }

        $token = config('whatsapp.verify_token');
        $providedToken = $headers['x-whatsapp-token'] ?? null;

        return is_string($token) && $token !== '' && is_string($providedToken)
            && hash_equals($token, $providedToken);
    }

    /** @param array<string, mixed> $payload */
    private function lockInbound(string $providerId, string $phone, string $body, array $payload): WhatsAppMessage
    {
        $existing = WhatsAppMessage::where('provider_message_id', $providerId)->lockForUpdate()->first();
        if ($existing) {
            return $existing;
        }

        try {
            return WhatsAppMessage::create([
                'provider_message_id' => $providerId,
                'direction' => 'inbound',
                'phone' => $phone,
                'message_type' => 'text',
                'body' => $body,
                'payload' => $payload,
                'status' => 'processing',
            ]);
        } catch (QueryException) {
            return WhatsAppMessage::where('provider_message_id', $providerId)->lockForUpdate()->firstOrFail();
        }
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->first() ?? $exception->getMessage();
    }
}
