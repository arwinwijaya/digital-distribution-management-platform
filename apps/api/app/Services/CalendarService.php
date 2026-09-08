<?php

namespace App\Services;

use App\Models\SalesVisit;

class CalendarService
{
    /**
     * The MVP has no external calendar dependency. This seam lets a calendar
     * adapter be injected later without coupling visit persistence to it.
     */
    public function schedule(SalesVisit $visit): void
    {
    }
}
