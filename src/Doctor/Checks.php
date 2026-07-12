<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Doctor;

use Simtabi\Laranail\Licence\Verifier\Support\ConnectionChecker;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\ConfigPresentCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\PhpExtensionCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\Checks\ReachabilityCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;

/**
 * The canonical license-verifier health checks — one list reused by the service
 * provider (unified doctor), the doctor command, and the HTTP health endpoint.
 */
final class Checks
{
    /**
     * @return list<DoctorCheck|class-string<DoctorCheck>>
     */
    public static function all(): array
    {
        return [
            DriverResolvesCheck::class,
            new ConfigPresentCheck(['LICENSE_VERIFIER_KEY' => 'license-verifier.license_key'], required: false, name: 'license-verifier:license-key', description: 'A license key is configured'),
            PublicKeyCheck::class,
            StorageWritableCheck::class,
            StorageBackendCheck::class,
            new PhpExtensionCheck('sodium', 'license-verifier:sodium', 'The PHP sodium extension is loaded'),
            new ReachabilityCheck(static fn (): bool => app(ConnectionChecker::class)->check(), 'license-verifier:server-reachable', target: 'License server'),
        ];
    }
}
