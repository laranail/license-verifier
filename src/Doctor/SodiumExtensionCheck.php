<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Doctor;

use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * The sodium extension is required for PASETO/Ed25519 crypto.
 */
final class SodiumExtensionCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'license-verifier:sodium';
    }

    public function description(): string
    {
        return 'The PHP sodium extension is loaded';
    }

    public function run(): DoctorResult
    {
        return extension_loaded('sodium')
            ? DoctorResult::pass('sodium loaded.')
            : DoctorResult::fail('ext-sodium is required for crypto.');
    }
}
