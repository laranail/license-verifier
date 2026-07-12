<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities;

/**
 * Driver can verify a license offline (no network round-trip) using a stored,
 * cryptographically signed token/license file.
 */
interface SupportsOfflineTokens
{
    public function isValidOffline(?string $key = null): bool;

    public function requiresOnlineRefresh(?string $key = null): bool;
}
