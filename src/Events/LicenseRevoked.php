<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched the first time verification resolves a revoked license.
 */
final readonly class LicenseRevoked
{
    use Dispatchable;

    public function __construct(
        public ?string $key = null,
        public ?string $licensedTo = null,
    ) {}
}
