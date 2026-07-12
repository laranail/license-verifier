<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities;

/**
 * Driver can list and revoke the seats/machine activations of a license.
 */
interface SupportsSeatManagement
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSeats(?string $key = null): array;

    /**
     * Revoke a seat by its id or device fingerprint.
     */
    public function revokeSeat(string $target, ?string $key = null): bool;
}
