<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Doctor;

use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * A license key should be configured for non-interactive verification.
 */
final class LicenseKeyCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'license-verifier:license-key';
    }

    public function description(): string
    {
        return 'A license key is configured';
    }

    public function run(): DoctorResult
    {
        return config('license-verifier.license_key')
            ? DoctorResult::pass('License key present.')
            : DoctorResult::warn('No LICENSE_VERIFIER_KEY set.');
    }
}
