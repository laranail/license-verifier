<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Doctor;

use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Throwable;

/**
 * The configured driver must resolve.
 */
final class DriverResolvesCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'license-verifier:driver';
    }

    public function description(): string
    {
        return 'The configured license driver resolves';
    }

    public function run(): DoctorResult
    {
        $manager = app(DriverManager::class);
        $name = $manager->getDefaultDriver();

        try {
            $manager->active();
        } catch (Throwable $e) {
            return DoctorResult::fail($e->getMessage());
        }

        return DoctorResult::pass("driver: {$name}");
    }
}
