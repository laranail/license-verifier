<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Doctor;

use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * Offline PASETO verification needs a public key.
 */
final class PublicKeyCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'license-verifier:public-key';
    }

    public function description(): string
    {
        return 'A public key is configured for offline PASETO verification';
    }

    public function run(): DoctorResult
    {
        if (config('license-verifier.public_key')) {
            return DoctorResult::pass('Public key present.');
        }

        return app(DriverManager::class)->getDefaultDriver() === 'paseto'
            ? DoctorResult::warn('Recommended for offline PASETO verification.')
            : DoctorResult::pass('Not required for this driver.');
    }
}
