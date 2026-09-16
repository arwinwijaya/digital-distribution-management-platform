<?php

namespace App\Services;

use App\Models\Order;

class PilotQualificationService
{
    public const MIN_OUTLETS = 10;

    public function evaluate(int $partnerId, ?array $qualificationAttributes = null): array
    {
        $outletCount = $this->countQualifiedOutlets($partnerId);
        $outletCheck = $outletCount >= self::MIN_OUTLETS ? 'PASS' : 'FAIL';

        $documentationCheck = $this->checkDocumentation($qualificationAttributes);
        $picCheck = $this->checkPic($qualificationAttributes);
        $internetCheck = $this->checkInternet($qualificationAttributes);

        $qualified = $outletCheck === 'PASS'
            && $documentationCheck === 'PASS'
            && $picCheck === 'PASS'
            && $internetCheck === 'PASS';

        return [
            'qualified' => $qualified,
            'outletCount' => $outletCount,
            'outletCheck' => $outletCheck,
            'documentationCheck' => $documentationCheck,
            'picCheck' => $picCheck,
            'internetCheck' => $internetCheck,
        ];
    }

    private function countQualifiedOutlets(int $partnerId): int
    {
        $since = now()->subDays(30);

        return (int) Order::query()
            ->where('created_at', '>=', $since)
            ->whereHas('outlet', function ($query) use ($partnerId) {
                $query->where('territory_id', $partnerId)->where('is_active', true);
            })
            ->distinct()
            ->count('outlet_id');
    }

    private function checkDocumentation(?array $qual): string
    {
        if ($qual === null) {
            return 'PASS';
        }

        return ! empty($qual['hasDocumentation']) ? 'PASS' : 'FAIL';
    }

    private function checkPic(?array $qual): string
    {
        if ($qual === null) {
            return 'PASS';
        }

        if (empty($qual['hasPic'])) {
            return 'FAIL';
        }

        $start = $qual['picAvailableStart'] ?? '08:00';
        $end = $qual['picAvailableEnd'] ?? '17:00';

        return ($start === '08:00' && $end === '17:00') ? 'PASS' : 'FAIL';
    }

    private function checkInternet(?array $qual): string
    {
        if ($qual === null) {
            return 'PASS';
        }

        return ! empty($qual['internetStable']) ? 'PASS' : 'FAIL';
    }
}
