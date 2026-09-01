<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Doctor;

use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Stores\FallbackLicenseStore;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;

/**
 * Report the storage backend and any active fallback / pending sync.
 */
final class StorageBackendCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'license-verifier:storage-backend';
    }

    public function description(): string
    {
        return 'The license storage backend is healthy';
    }

    public function run(): DoctorResult
    {
        $driver = (string) config('license-verifier.storage.driver', 'file');
        $fallback = config('license-verifier.storage.fallback');

        $store = app(LicenseStore::class);

        if ($store instanceof FallbackLicenseStore) {
            $pending = $store->pendingSyncCount();

            if ($store->onFallback()) {
                return DoctorResult::warn("{$driver} unreachable — serving local fallback ({$pending} pending sync)");
            }

            return DoctorResult::pass("{$driver} (primary) + {$fallback} fallback".($pending > 0 ? " — {$pending} pending sync" : ''));
        }

        return DoctorResult::pass($driver);
    }
}
