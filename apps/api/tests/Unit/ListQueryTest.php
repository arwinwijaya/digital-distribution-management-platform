<?php

namespace Tests\Unit;

use App\Support\ListQuery;
use Tests\TestCase;

class ListQueryTest extends TestCase
{
    public function test_resolve_sort_returns_allowlisted_column_and_order(): void
    {
        $this->assertSame(
            ['name', 'asc'],
            ListQuery::resolveSort(['created_at', 'name', 'id'], 'name', 'asc')
        );
    }

    public function test_resolve_sort_falls_back_to_default_for_invalid_column(): void
    {
        $this->assertSame(
            ['created_at', 'desc'],
            ListQuery::resolveSort(['created_at', 'name', 'id'], '__proto__', 'desc')
        );
    }

    public function test_resolve_sort_falls_back_to_desc_for_invalid_order(): void
    {
        $this->assertSame(
            ['name', 'desc'],
            ListQuery::resolveSort(['created_at', 'name', 'id'], 'name', 'DROP')
        );
    }

    public function test_raw_order_builds_portable_nulls_last_for_desc(): void
    {
        $this->assertSame(
            '(created_at IS NULL) ASC, created_at DESC, id DESC',
            ListQuery::rawOrder('created_at', 'desc')
        );
    }

    public function test_raw_order_builds_portable_nulls_last_for_asc(): void
    {
        $this->assertSame(
            '(created_at IS NULL) ASC, created_at ASC, id DESC',
            ListQuery::rawOrder('created_at', 'asc')
        );
    }

    public function test_offset_clamps_null_to_zero(): void
    {
        $this->assertSame(0, ListQuery::offset(null, 15));
    }

    public function test_offset_passes_through_positive_value(): void
    {
        $this->assertSame(45, ListQuery::offset(45, 15));
    }

    public function test_offset_clamps_negative_to_zero(): void
    {
        $this->assertSame(0, ListQuery::offset(-5, 15));
    }

    public function test_meta_includes_total_and_summary(): void
    {
        $this->assertSame(
            ['total' => 48, 'summary' => ['active' => 32, 'inactive' => 16]],
            ListQuery::meta(48, ['active' => 32, 'inactive' => 16])
        );
    }

    public function test_meta_omits_summary_when_empty(): void
    {
        $meta = ListQuery::meta(12, []);

        $this->assertSame(12, $meta['total']);
        $this->assertArrayNotHasKey('summary', $meta);
    }
}
