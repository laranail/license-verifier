<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a license token is successfully refreshed.
 */
final readonly class LicenseRefreshed
{
    use Dispatchable;

    public function __construct(
        public ?string $key = null,
        public ?string $licensedTo = null,
    ) {}
}
