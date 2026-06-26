<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities;

/**
 * Driver tracks seat/machine usage against the license.
 */
interface SupportsSeats
{
    public function seatsUsed(?string $key = null): ?int;

    public function seatsTotal(?string $key = null): ?int;
}
