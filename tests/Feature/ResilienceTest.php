<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Verifier\Bindings\DomainBinding;
use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Licence\Verifier\Exceptions\LicensingException;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * A scripted driver whose verify() can succeed once and then fail, to exercise
 * the cache + grace/fail-open window in the orchestrator.
 */
function scriptedDriver(callable $verify): Driver
{
    return new class($verify) implements Driver
    {
        public function __construct(private $verify) {}

        public function name(): string
        {
            return 'scripted';
        }

        public function activate(LicenseRequest $request): VerificationResult
        {
            return VerificationResult::valid();
        }

        public function verify(?string $key = null): VerificationResult
        {
            return ($this->verify)();
        }

        public function deactivate(?string $key = null, ?string $reason = null): bool
        {
            return true;
        }

        public function getLicenseInfo(?string $key = null): LicenseInfo
        {
            return LicenseInfo::empty();
        }

        public function health(): bool
        {
            return true;
        }

        public function activationFields(): array
        {
            return [];
        }

        public function capabilities(): array
        {
            return [];
        }
    };
}

function managerForDriver(Driver $driver): LicenseManager
{
    $manager = Mockery::mock(DriverManager::class);
    $manager->shouldReceive('active')->andReturn($driver);
    $manager->shouldReceive('getDefaultDriver')->andReturn('scripted');

    return new LicenseManager($manager, app(DomainBinding::class));
}

beforeEach(function (): void {
    config()->set('license-verifier.cache.store', 'array');
    config()->set('license-verifier.cache.enabled', true);
    config()->set('license-verifier.cache.ttl', 0); // never "fresh" → always re-checks the driver
    config()->set('license-verifier.grace_period_days', 7);
});

it('falls open to grace within the window when the source becomes unreachable', function (): void {
    config()->set('license-verifier.security.fail_open_in_grace', true);

    $calls = 0;
    $manager = managerForDriver(scriptedDriver(function () use (&$calls): VerificationResult {
        $calls++;

        // First call succeeds (caches a last-good result); subsequent calls throw.
        if ($calls === 1) {
            return VerificationResult::valid();
        }

        throw LicensingException::serverUnreachable();
    }));

    expect($manager->verify('K')->status)->toBe(LicenseStatus::Valid);   // cached as last-good
    expect($manager->verify('K')->status)->toBe(LicenseStatus::Grace);   // unreachable → grace
});

it('fails closed when fail_open_in_grace is disabled', function (): void {
    config()->set('license-verifier.security.fail_open_in_grace', false);

    $calls = 0;
    $manager = managerForDriver(scriptedDriver(function () use (&$calls): VerificationResult {
        $calls++;

        return $calls === 1
            ? VerificationResult::valid()
            : throw LicensingException::serverUnreachable();
    }));

    expect($manager->verify('K')->status)->toBe(LicenseStatus::Valid);
    expect($manager->verify('K')->status)->toBe(LicenseStatus::Unreachable);
});

it('serves a fresh cached result without calling the driver', function (): void {
    config()->set('license-verifier.cache.ttl', 3600);

    $calls = 0;
    $manager = managerForDriver(scriptedDriver(function () use (&$calls): VerificationResult {
        $calls++;

        return VerificationResult::valid();
    }));

    $manager->verify('K');
    $manager->verify('K');

    expect($calls)->toBe(1); // second call served from the fresh cache
});

it('rejects a usable license on a disallowed domain', function (): void {
    config()->set('license-verifier.bindings.domain.enabled', true);
    config()->set('license-verifier.bindings.domain.allowed', ['allowed.example']);

    $manager = managerForDriver(scriptedDriver(fn (): VerificationResult => VerificationResult::valid()));

    // app URL host (localhost) is not in the allowlist → downgraded to Invalid.
    expect($manager->verify('K')->status)->toBe(LicenseStatus::Invalid);
});
