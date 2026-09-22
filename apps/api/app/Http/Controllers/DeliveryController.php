<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryRequest;
use App\Http\Requests\StoreLocationPingRequest;
use App\Http\Requests\UpdateDeliveryStatusRequest;
use App\Http\Requests\UploadProofRequest;
use App\Models\Delivery;
use App\Models\DeliveryLocationPing;
use App\Models\Order;
use App\Models\User;
use App\Services\FinanceAuthorizationService;
use App\Services\OutletScoringService;
use App\Services\RoutingService;
use App\Support\ConcurrencyTestBarrier;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DeliveryController extends Controller
{
    public function __construct(
        private readonly RoutingService $routing,
        private readonly OutletScoringService $scoring,
        private readonly FinanceAuthorizationService $authorization,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'sales', 'driver'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $query = Delivery::with(self::RELATIONS);
        if ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        } elseif ($user->isSales()) {
            $query->where('assigned_by_id', $user->id);
        }

        return response()->json(['status' => 'success', 'data' => $query->latest()->get()->map(fn (Delivery $delivery) => $this->format($delivery))]);
    }

    public function store(StoreDeliveryRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $actor = $request->user();

        try {
            $delivery = DB::transaction(function () use ($validated, $actor): Delivery {
                $order = Order::lockForUpdate()->find($validated['order_id']);
                if (!$order) {
                    abort(response()->json(['status' => 'error', 'message' => 'Order not found.'], 404));
                }
                if ($order->status !== 'Confirmed') {
                    abort(response()->json(['status' => 'error', 'message' => 'Only confirmed orders can be assigned for delivery.'], 422));
                }
                if (Delivery::where('order_id', $order->id)->exists()) {
                    abort(response()->json(['status' => 'error', 'message' => 'This order already has a delivery assignment.'], 422));
                }

                $driver = User::lockForUpdate()->find($validated['driver_id']);
                if (!$driver || $driver->role !== 'driver' || !$driver->is_active) {
                    abort(response()->json(['status' => 'error', 'message' => 'An active driver is required.'], 422));
                }

                $delivery = Delivery::create([
                    'order_id' => $order->id,
                    'driver_id' => $driver->id,
                    'assigned_by_id' => $actor->id,
                    'status' => Delivery::ASSIGNED,
                    'assigned_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);
                $delivery->route_data = $this->routing->plan($delivery) ?: null;
                $delivery->save();
                $delivery->statusHistory()->create([
                    'actor_id' => $actor->id,
                    'from_status' => null,
                    'status' => Delivery::ASSIGNED,
                    'metadata' => ['driver_id' => $driver->id],
                    'notes' => $validated['notes'] ?? null,
                ]);

                return $delivery;
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return response()->json(['status' => 'error', 'message' => 'This order already has a delivery assignment.'], 422);
            }
            throw $exception;
        }

        return response()->json(['status' => 'success', 'data' => $this->format($delivery->load(self::RELATIONS))], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $delivery = Delivery::with(self::RELATIONS)->findOrFail($id);
        if (!$this->canView($request->user(), $delivery)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        return response()->json(['status' => 'success', 'data' => $this->format($delivery)]);
    }

    public function updateStatus(UpdateDeliveryStatusRequest $request, int $id): JsonResponse
    {
        $actor = $request->user();
        $data = $request->validated();
        $delivery = DB::transaction(function () use ($id, $actor, $data): Delivery {
            $delivery = Delivery::lockForUpdate()->findOrFail($id);
            if (!$this->canMutate($actor, $delivery)) {
                abort(response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403));
            }
            if (!Delivery::canTransition($delivery->status, $data['status'])) {
                abort(response()->json(['status' => 'error', 'message' => "Cannot transition delivery from {$delivery->status} to {$data['status']}.", 'current_status' => $delivery->status], 422));
            }

            $order = null;
            if ($data['status'] === Delivery::DELIVERED) {
                // Keep the order lock in this transaction before changing either
                // delivery state or the order status history. Gated so production
                // never pauses inside this critical section.
                if (config('orders.concurrency_barrier_enabled', false)) {
                    ConcurrencyTestBarrier::await('payment');
                }
                $order = Order::lockForUpdate()->findOrFail($delivery->order_id);
            }

            $delivery->fill(array_intersect_key($data, array_flip([
                'recipient_name', 'proof_of_delivery_url', 'proof_of_delivery', 'failure_reason', 'notes',
            ])));
            $metadata = array_intersect_key($data, array_flip(['recipient_name', 'proof_of_delivery_url', 'proof_of_delivery', 'failure_reason']));
            $delivery->transitionTo($data['status'], $actor, $metadata, $data['notes'] ?? null);
            $delivery->save();
            if ($data['status'] === Delivery::DELIVERED && $order?->status !== 'Delivered') {
                $order->recordStatus('Delivered', 'Delivery completed');
            }
            return $delivery;
        });

        return response()->json(['status' => 'success', 'data' => $this->format($delivery->fresh(self::RELATIONS))]);
    }

    /**
     * Driver GPS breadcrumb for their own delivery (Phase 8, T7).
     */
    public function storeLocation(StoreLocationPingRequest $request, int $id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);
        $actor = $request->user();

        if (! $actor->isAdmin() && $delivery->driver_id !== $actor->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $ping = $delivery->locationPings()->create([
            'latitude' => $request->float('latitude'),
            'longitude' => $request->float('longitude'),
            'accuracy_m' => $request->integer('accuracy_m') ?: null,
            'recorded_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'data' => $this->formatPing($ping)], 201);
    }

    /**
     * Proof-of-delivery upload (photo + signature) via the disk abstraction (T8).
     *
     * Only the assigned driver (or an admin) may attach media, and only while the
     * delivery is still open (assigned/in_progress). Files are stored on
     * `filesystems.default` and referenced by URL in `proof_of_delivery`.
     */
    public function uploadProof(UploadProofRequest $request, int $id): JsonResponse
    {
        $delivery = Delivery::findOrFail($id);
        $actor = $request->user();

        if (! $actor->isAdmin() && $delivery->driver_id !== $actor->id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        if (! in_array($delivery->status, [Delivery::ASSIGNED, Delivery::IN_PROGRESS], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Proof of delivery can only be attached to an open delivery.',
            ], 422);
        }

        $diskName = config('filesystems.default');
        $disk = Storage::disk($diskName);
        $capturedAt = now();

        $photoPath = $request->file('photo')->store('proof-of-delivery/'.$delivery->id, $diskName);
        $signaturePath = $request->file('signature')->store('proof-of-delivery/'.$delivery->id, $diskName);

        $delivery->fill([
            'proof_of_delivery' => [
                'photo_url' => $disk->url($photoPath),
                'signature_url' => $disk->url($signaturePath),
                'photo_path' => $photoPath,
                'signature_path' => $signaturePath,
                'captured_at' => $capturedAt->toIso8601String(),
            ],
            'proof_of_delivery_url' => $disk->url($photoPath),
            'pod_captured_at' => $capturedAt,
            'pod_latitude' => $request->input('latitude'),
            'pod_longitude' => $request->input('longitude'),
        ]);
        $delivery->save();

        return response()->json(['status' => 'success', 'data' => $this->format($delivery->fresh(self::RELATIONS))], 201);
    }

    /**
     * Admin read of the scheduled route plan for a delivery (Phase 8, T9).
     *
     * `route_data` is a nullable snapshot; a null plan is a valid response.
     */
    public function route(Request $request, int $id): JsonResponse
    {
        $this->authorization->assertAdminOrOwner($request->user());

        $delivery = Delivery::with(['driver:id,name', 'order.outlet:id,name,latitude,longitude'])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'delivery_id' => $delivery->id,
                'driver' => $delivery->driver ? [
                    'id' => $delivery->driver->id,
                    'name' => $delivery->driver->name,
                ] : null,
                'route_data' => $delivery->route_data,
            ],
        ]);
    }

    /**
     * Admin live-track read: latest position + newest-first bounded pings (T7).
     *
     * `field_ops:read` is also granted to sales/driver for their own surfaces, so
     * the dispatcher view additionally asserts an admin/owner role here.
     */
    public function track(Request $request, int $id): JsonResponse
    {
        $this->authorization->assertAdminOrOwner($request->user());

        $delivery = Delivery::findOrFail($id);

        $pings = $delivery->locationPings()
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(self::TRACK_PING_LIMIT)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'delivery_id' => $delivery->id,
                'status' => $delivery->status,
                'driver' => $delivery->driver ? [
                    'id' => $delivery->driver->id,
                    'name' => $delivery->driver->name,
                ] : null,
                'last_position' => $pings->isNotEmpty() ? $this->formatPing($pings->first()) : null,
                'pings' => $pings->map(fn (DeliveryLocationPing $ping) => $this->formatPing($ping))->values(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPing(DeliveryLocationPing $ping): array
    {
        return [
            'id' => $ping->id,
            'latitude' => $ping->latitude,
            'longitude' => $ping->longitude,
            'accuracy_m' => $ping->accuracy_m,
            'recorded_at' => $ping->recorded_at,
        ];
    }

    /**
     * Eager-load set shared by every `format()` call site. `order.outlet`,
     * `order.items.product` and `order.salesUser` feed the enriched detail
     * payload without triggering lazy loads on the store/show/updateStatus paths.
     *
     * @var list<string>
     */
    private const RELATIONS = [
        'order.outlet',
        'order.items.product',
        'order.salesUser:id,name,email',
        'driver:id,name,email',
        'assignedBy:id,name,email',
        'statusHistory.actor:id,name,role',
    ];

    /**
     * Upper bound on breadcrumbs returned by the admin track endpoint.
     */
    private const TRACK_PING_LIMIT = 50;

    private function canView(User $user, Delivery $delivery): bool
    {
        return $user->isAdmin()
            || ($user->isDriver() && $delivery->driver_id === $user->id)
            || ($user->isSales() && $delivery->assigned_by_id === $user->id);
    }

    private function canMutate(User $user, Delivery $delivery): bool
    {
        return $user->isAdmin()
            || ($user->isDriver() && $delivery->driver_id === $user->id)
            || ($user->isSales() && $delivery->assigned_by_id === $user->id);
    }

    private function format(Delivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'order_id' => $delivery->order_id,
            'driver_id' => $delivery->driver_id,
            'assigned_by_id' => $delivery->assigned_by_id,
            'status' => $delivery->status,
            'assigned_at' => $delivery->assigned_at,
            // Local (app tz) calendar date — the default serializer emits UTC,
            // which would misfile 00:00–06:59 WIB deliveries to the prior day.
            'assigned_date' => $delivery->assigned_at?->toDateString(),
            'started_at' => $delivery->started_at,
            'delivered_at' => $delivery->delivered_at,
            'failure_reason' => $delivery->failure_reason,
            'recipient_name' => $delivery->recipient_name,
            'proof_of_delivery_url' => $delivery->proof_of_delivery_url,
            'proof_of_delivery' => $delivery->proof_of_delivery,
            'pod_captured_at' => $delivery->pod_captured_at,
            'pod_latitude' => $delivery->pod_latitude,
            'pod_longitude' => $delivery->pod_longitude,
            'notes' => $delivery->notes,
            'route_data' => $delivery->route_data,
            'order' => $this->formatOrder($delivery->order),
            'driver' => $delivery->driver,
            'assigned_by' => $delivery->assignedBy,
            'status_history' => $delivery->statusHistory->map(fn ($history) => [
                'id' => $history->id,
                'from_status' => $history->from_status,
                'status' => $history->status,
                'actor_id' => $history->actor_id,
                'metadata' => $history->metadata,
                'notes' => $history->notes,
                'created_at' => $history->created_at,
            ])->values(),
            'created_at' => $delivery->created_at,
            'updated_at' => $delivery->updated_at,
        ];
    }

    /**
     * Explicit, null-safe order projection for the delivery payload. Keeps the
     * response additive (existing keys preserved) while exposing the outlet,
     * sales rep and item names the delivery detail view needs. Mirrors the
     * item mapping in {@see OrderController::format}.
     *
     * @return array<string, mixed>|null
     */
    private function formatOrder(?Order $order): ?array
    {
        if (!$order) {
            return null;
        }

        return [
            'id' => $order->id,
            'order_id' => $order->order_id,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'outlet' => $order->outlet ? [
                'id' => $order->outlet->id,
                'name' => $order->outlet->name,
                'city' => $order->outlet->city,
                'address' => $order->outlet->address,
            ] : null,
            'sales' => $order->salesUser ? [
                'id' => $order->salesUser->id,
                'name' => $order->salesUser->name,
            ] : null,
            'items' => $order->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? null,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
            ])->values(),
        ];
    }
}
