<?php

return [
    // Revenue hook: this rate is snapshotted on every order so historical
    // transactions are not changed when the configured rate changes.
    'commission_percentage' => (float) env('ORDER_COMMISSION_PERCENTAGE', 2.00),
];
