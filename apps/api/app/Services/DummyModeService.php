<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Dummy mode accessor for the deterministic invoice payload cached by
 * DummySeeder. Zero network — only reads from local cache.
 */
class DummyModeService
{
    public const DETAIL_CACHE_KEY = 'dummy.invoice.detail';

    public function enabled(): bool
    {
        return (bool) config('app.dummy_mode', false);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function detail(): ?array
    {
        $detail = Cache::get(self::DETAIL_CACHE_KEY);
        return is_array($detail) ? $detail : null;
    }
}