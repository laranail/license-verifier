<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities;

/**
 * Driver can send periodic heartbeats to the source.
 */
interface SupportsHeartbeat
{
    public function heartbeat(?string $key = null): bool;
}
