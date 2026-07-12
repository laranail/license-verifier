<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a seat/machine is revoked from a license.
 */
final readonly class LicenseSeatRevoked
{
    use Dispatchable;

    public function __construct(
        public ?string $target = null,
        public ?string $key = null,
    ) {}
}
