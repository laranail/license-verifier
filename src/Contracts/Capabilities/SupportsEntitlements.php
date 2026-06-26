<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities;

/**
 * Driver exposes feature entitlements attached to the license.
 */
interface SupportsEntitlements
{
    /**
     * @return array<string, mixed>
     */
    public function entitlements(?string $key = null): array;

    public function entitledTo(string $feature, ?string $key = null): bool;
}
