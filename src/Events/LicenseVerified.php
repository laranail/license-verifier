<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class LicenseVerified
{
    use Dispatchable;

    public function __construct(
        public ?string $key = null,
        public ?string $licensedTo = null,
    ) {}
}
