<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class LicenseExpiringSoon
{
    use Dispatchable;

    public function __construct(
        public ?string $key = null,
        public ?int $daysRemaining = null,
        public ?string $expiresAt = null,
    ) {}
}
