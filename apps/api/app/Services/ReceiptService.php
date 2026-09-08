<?php

namespace App\Services;

use App\Models\Payment;

class ReceiptService
{
    public function generate(Payment $payment): string
    {
        return 'RCT-'.strtoupper(bin2hex(random_bytes(6)));
    }
}
