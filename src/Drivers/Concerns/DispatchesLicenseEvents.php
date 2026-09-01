<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers\Concerns;

use Simtabi\Laranail\Licence\Verifier\Events\GracePeriodStarted;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseActivated;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseActivating;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseDeactivated;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseDeactivating;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseHeartbeatSent;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseInvalid;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseRefreshed;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseRevoked;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseSeatRevoked;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseUnverified;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseVerified;

/**
 * Shared lifecycle-event dispatching for drivers.
 */
trait DispatchesLicenseEvents
{
    protected function eventActivating(?string $key, ?string $client = null): void
    {
        if ($this->eventsEnabled()) {
            LicenseActivating::dispatch($key, $client);
        }
    }

    protected function eventActivated(?string $key, ?string $licensedTo = null): void
    {
        if ($this->eventsEnabled()) {
            LicenseActivated::dispatch($key, $licensedTo);
        }
    }

    protected function eventDeactivating(?string $key): void
    {
        if ($this->eventsEnabled()) {
            LicenseDeactivating::dispatch($key);
        }
    }

    protected function eventDeactivated(?string $key): void
    {
        if ($this->eventsEnabled()) {
            LicenseDeactivated::dispatch($key);
        }
    }

    protected function eventVerified(?string $key, ?string $licensedTo = null): void
    {
        if ($this->eventsEnabled()) {
            LicenseVerified::dispatch($key, $licensedTo);
        }
    }

    protected function eventUnverified(?string $key): void
    {
        if ($this->eventsEnabled()) {
            LicenseUnverified::dispatch($key);
        }
    }

    protected function eventInvalid(?string $key): void
    {
        if ($this->eventsEnabled()) {
            LicenseInvalid::dispatch($key);
        }
    }

    protected function eventRevoked(?string $key, ?string $licensedTo = null): void
    {
        if ($this->eventsEnabled()) {
            LicenseRevoked::dispatch($key, $licensedTo);
        }
    }

    protected function eventGraceStarted(?string $key, ?string $licensedTo = null): void
    {
        if ($this->eventsEnabled()) {
            GracePeriodStarted::dispatch($key, $licensedTo);
        }
    }

    protected function eventRefreshed(?string $key, ?string $licensedTo = null): void
    {
        if ($this->eventsEnabled()) {
            LicenseRefreshed::dispatch($key, $licensedTo);
        }
    }

    protected function eventHeartbeatSent(?string $key): void
    {
        if ($this->eventsEnabled()) {
            LicenseHeartbeatSent::dispatch($key);
        }
    }

    protected function eventSeatRevoked(?string $target, ?string $key = null): void
    {
        if ($this->eventsEnabled()) {
            LicenseSeatRevoked::dispatch($target, $key);
        }
    }

    private function eventsEnabled(): bool
    {
        return (bool) config('license-verifier.events.enabled', true);
    }
}
