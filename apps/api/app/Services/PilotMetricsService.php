<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class PilotMetricsService
{
    public const MIN_VALID_ORDERS = 20;
    public const EXTEND_DAYS = 3;
    public const SPEED_TARGET_PERCENT = -30;
    public const ERROR_RATE_LIMIT = 5.0;
    public const DELIVERY_SUCCESS_LIMIT = 95.0;
    public const PAYMENT_COMPLETION_LIMIT = 90.0;

    /**
     * @param  iterable<Order>|Collection  $orders
     * @return array{count: int, cancelledCount: int, validCount: int, breakdown: array<string,int>}
     */
    public function countValidOrders(iterable $orders): array
    {
        $list = $orders instanceof Collection ? $orders : collect($orders);
        $count = $list->count();
        $cancelled = $list->where('status', 'Cancelled')->count();
        $valid = $count - $cancelled;

        return [
            'count' => $count,
            'cancelledCount' => $cancelled,
            'validCount' => $valid,
            'breakdown' => [
                'valid' => $valid,
                'cancelled' => $cancelled,
                'total' => $count,
            ],
        ];
    }

    /**
     * @return array{targetMet: bool, validOrderCount: int, pilotDays: int, extendDays: int, newDeadlineDay: int}
     */
    public function evaluateVolume(int $validOrderCount, int $pilotDays): array
    {
        $targetMet = $validOrderCount >= self::MIN_VALID_ORDERS;
        $extendDays = $targetMet ? 0 : self::EXTEND_DAYS;

        return [
            'targetMet' => $targetMet,
            'validOrderCount' => $validOrderCount,
            'pilotDays' => $pilotDays,
            'extendDays' => $extendDays,
            'newDeadline' => $pilotDays + $extendDays,
            'newDeadlineDay' => $pilotDays + $extendDays,
        ];
    }

    /**
     * @return array{deltaPercent: float, delta: float, targetMet: bool, baselineHours: float, platformHours: float, targetPercent: float}
     */
    public function calculateSpeedDelta(float $baselineHours, float $platformHours): array
    {
        if ($baselineHours == 0.0) {
            $deltaPercent = 0.0;
        } else {
            $deltaPercent = round((($platformHours - $baselineHours) / $baselineHours) * 100, 1);
        }

        $targetMet = $deltaPercent <= self::SPEED_TARGET_PERCENT;

        return [
            'deltaPercent' => $deltaPercent,
            'delta' => $deltaPercent,
            'baselineHours' => $baselineHours,
            'platformHours' => $platformHours,
            'targetPercent' => (float) self::SPEED_TARGET_PERCENT,
            'targetMet' => $targetMet,
        ];
    }

    /**
     * Accepts payload shape: ['total'=>int,'errors'=>int,'delivered'=>int,'paid'=>int]
     * and also tolerates alternate keys ['valid_orders'=>int,'error_count'=>int].
     *
     * @return array{errorRate: float, deliverySuccess: float, paymentCompletion: float, guardrailsPassed: bool, errorCheck: string, deliveryCheck: string, paymentCheck: string}
     */
    public function calculateReliability(array $metrics): array
    {
        $total = (int) ($metrics['total'] ?? $metrics['valid_orders'] ?? $metrics['validOrderCount'] ?? 0);
        $errors = (int) ($metrics['errors'] ?? $metrics['error_count'] ?? $metrics['errorCount'] ?? 0);
        $delivered = (int) ($metrics['delivered'] ?? $metrics['delivered_count'] ?? 0);
        $paid = (int) ($metrics['paid'] ?? $metrics['paid_count'] ?? $metrics['paymentCount'] ?? 0);

        // If total is 0, fall back to delivered denominator where applicable, but keep 0 for calculations.
        $errorRate = $total > 0 ? round(($errors / $total) * 100, 1) : 0.0;
        $deliverySuccess = $total > 0 ? round(($delivered / $total) * 100, 1) : 0.0;
        // Payment completion denominator is delivered orders (as per spec "23 paid / 24 delivered").
        $deliveryDenominator = $delivered > 0 ? $delivered : $total;
        $paymentCompletion = $deliveryDenominator > 0 ? round(($paid / $deliveryDenominator) * 100, 1) : 0.0;

        $errorCheck = $errorRate < self::ERROR_RATE_LIMIT ? 'PASS' : 'FAIL';
        $deliveryCheck = $deliverySuccess > self::DELIVERY_SUCCESS_LIMIT ? 'PASS' : 'FAIL';
        $paymentCheck = $paymentCompletion > self::PAYMENT_COMPLETION_LIMIT ? 'PASS' : 'FAIL';

        $guardrailsPassed = $errorCheck === 'PASS' && $deliveryCheck === 'PASS' && $paymentCheck === 'PASS';

        return [
            'errorRate' => $errorRate,
            'deliverySuccess' => $deliverySuccess,
            'paymentCompletion' => $paymentCompletion,
            'guardrailsPassed' => $guardrailsPassed,
            'errorCheck' => $errorCheck,
            'deliveryCheck' => $deliveryCheck,
            'paymentCheck' => $paymentCheck,
        ];
    }

    /**
     * Returns platform hours between order created_at and delivered_at (via delivery or order status transition).
     * Wraps common aliases for the red-test's chaining with calculateSpeedDelta().
     *
     * @return array{platformHours: float, deliveredAt: ?Carbon, createdAt: ?Carbon}
     */
    public function measureLifecycleTiming(int $orderId): array
    {
        $order = Order::findOrFail($orderId);
        $delivery = Delivery::where('order_id', $order->id)->first();

        $createdAt = Carbon::parse($order->created_at);
        $deliveredAt = $delivery?->delivered_at
            ? Carbon::parse($delivery->delivered_at)
            : Carbon::parse($order->statusHistory()->where('status', 'Delivered')->value('created_at') ?? $order->updated_at);

        $platformHours = round($createdAt->diffInMinutes($deliveredAt) / 60.0, 2);

        return [
            'platformHours' => $platformHours,
            'deliveredAt' => $deliveredAt,
            'createdAt' => $createdAt,
        ];
    }
}
