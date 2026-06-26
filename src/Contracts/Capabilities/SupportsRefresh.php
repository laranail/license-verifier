<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities;

/**
 * Driver can refresh/renew its stored token from the source.
 */
interface SupportsRefresh
{
    public function refresh(?string $key = null): bool;
}
