<?php

namespace App\Services\Recommendation;

use RuntimeException;

/**
 * Sentinel used to carry adapter output out of a transaction that is then
 * rolled back. Never thrown out of the resolver.
 */
class AdapterReadOnlyResult extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $output
     */
    public function __construct(public readonly array $output)
    {
        parent::__construct('adapter read-only sentinel', 0, null);
    }
}
