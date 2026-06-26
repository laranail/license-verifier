<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts;

/**
 * Resolves where the application's license key (and optional details) come from:
 * config/env, a database model, or a custom callback. Selected by
 * config('license-verifier.source').
 */
interface LicenseKeyResolver
{
    public function resolve(): ?string;

    /**
     * Optional extra details discovered alongside the key (e.g. licensed_to).
     *
     * @return array<string, mixed>
     */
    public function details(): array;
}
