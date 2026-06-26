<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a heartbeat is successfully sent to the license server.
 */
final readonly class LicenseHeartbeatSent
{
    use Dispatchable;

    public function __construct(
        public ?string $key = null,
    ) {}
}
