<?php

namespace App\Services;

class PrePilotFeatureGate
{
    public function isEnabled(): bool
    {
        return (bool) config('pre_pilot.enabled') && ! (bool) config('pre_pilot.kill_switch');
    }
}
