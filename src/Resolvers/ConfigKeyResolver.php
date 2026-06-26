<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Resolvers;

use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseKeyResolver;

/**
 * Resolves the license key from config/env (license-verifier.license_key).
 */
final class ConfigKeyResolver implements LicenseKeyResolver
{
    public function resolve(): ?string
    {
        $key = config('license-verifier.license_key');

        return $key !== null && $key !== '' ? (string) $key : null;
    }

    public function details(): array
    {
        return array_filter([
            'licensed_to' => config('license-verifier.licensed_to'),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }
}
