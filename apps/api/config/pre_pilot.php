<?php

return [
    'enabled' => (bool) env('PRE_PILOT_ENABLED', false),
    'kill_switch' => (bool) env('PRE_PILOT_KILL_SWITCH', false),
];
