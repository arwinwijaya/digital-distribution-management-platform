<?php

namespace App\Services;

use App\Models\Delivery;

class RoutingService
{
    /**
     * MVP routing seam. Route optimization is intentionally out of scope;
     * callers can replace this service with an external adapter later.
     */
    public function plan(Delivery $delivery): array
    {
        return [];
    }
}
