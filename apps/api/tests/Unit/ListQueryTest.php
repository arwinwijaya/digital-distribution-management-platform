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
}
