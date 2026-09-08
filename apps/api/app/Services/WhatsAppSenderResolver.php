<?php

namespace App\Services;

use App\Models\Outlet;

class WhatsAppSenderResolver
{
    /** @return array{outlet: Outlet|null, ambiguous: bool} */
    public function resolve(string $phone): array
    {
        $normalized = Outlet::canonicalizePhone($phone);
        if ($normalized === '') {
            return ['outlet' => null, 'ambiguous' => false];
        }

        // Compare both the canonical column and legacy raw values. This keeps
        // old conflicting rows visible as ambiguous instead of selecting one.
        $matches = Outlet::query()->where('is_active', true)->get()->filter(
            fn (Outlet $outlet): bool => Outlet::canonicalizePhone((string) $outlet->phone) === $normalized
                || (string) $outlet->canonical_phone === $normalized
        )->values();

        if ($matches->count() !== 1) {
            return ['outlet' => null, 'ambiguous' => $matches->count() > 1];
        }

        return ['outlet' => $matches->first(), 'ambiguous' => false];
    }
}
