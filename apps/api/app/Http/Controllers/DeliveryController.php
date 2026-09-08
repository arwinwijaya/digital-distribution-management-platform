<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryRequest;
use App\Http\Requests\UpdateDeliveryStatusRequest;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Services\RoutingService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeliveryController extends Controller
{
    public function __construct(private readonly RoutingService $routing)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!in_array($user->role, ['admin', 'sales', 'driver'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized.'], 403);
        }

        $query = Delivery::with(['order', 'driver:id,name,email', 'assignedBy:id,name,email', 'statusHistory.actor:id,name,role']);
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

        return response()->json(['status' => 'success', 'data' => $this->format($delivery->load(['order', 'driver:id,name,email', 'assignedBy:id,name,email', 'statusHistory.actor:id,name,role']))], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $delivery = Delivery::with(['order', 'driver:id,name,email', 'assignedBy:id,name,email', 'statusHistory.actor:id,name,role'])->findOrFail($id);
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

            $delivery->fill(array_intersect_key($data, array_flip([
                'recipient_name', 'proof_of_delivery_url', 'proof_of_delivery', 'failure_reason', 'notes',
            ])));
            $metadata = array_intersect_key($data, array_flip(['recipient_name', 'proof_of_delivery_url', 'proof_of_delivery', 'failure_reason']));
            $delivery->transitionTo($data['status'], $actor, $metadata, $data['notes'] ?? null);
            $delivery->save();
            return $delivery;
        });

        return response()->json(['status' => 'success', 'data' => $this->format($delivery->fresh(['order', 'driver:id,name,email', 'assignedBy:id,name,email', 'statusHistory.actor:id,name,role']))]);
    }

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
            'started_at' => $delivery->started_at,
            'delivered_at' => $delivery->delivered_at,
            'failure_reason' => $delivery->failure_reason,
            'recipient_name' => $delivery->recipient_name,
            'proof_of_delivery_url' => $delivery->proof_of_delivery_url,
            'proof_of_delivery' => $delivery->proof_of_delivery,
            'notes' => $delivery->notes,
            'route_data' => $delivery->route_data,
            'order' => $delivery->order,
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
}
