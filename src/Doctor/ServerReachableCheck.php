<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Doctor;

use Simtabi\Laranail\Licence\Verifier\Support\ConnectionChecker;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Throwable;

/**
 * The license server should be reachable (cached; a miss — or an inability to
 * determine reachability — is a warning, never a configuration failure).
 */
final class ServerReachableCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'license-verifier:server-reachable';
    }

    public function description(): string
    {
        return 'The license server is reachable';
    }

    public function run(): DoctorResult
    {
        try {
            $reachable = app(ConnectionChecker::class)->check();
        } catch (Throwable $e) {
            return DoctorResult::warn('Could not determine server reachability.', ['error' => $e->getMessage()]);
        }

        return $reachable
            ? DoctorResult::pass('License server reachable.')
            : DoctorResult::warn('License server unreachable (cached result).');
    }
}
