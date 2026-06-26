<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Licence\Verifier\Bindings\DomainBinding;
use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseActivated;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseActivating;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseDeactivated;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseUnverified;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseVerified;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Lifecycle events are dispatched centrally by the LicenseManager, so EVERY
 * driver (not just PASETO) fires them.
 */
function fakeDriver(bool $valid): Driver
{
    return new readonly class($valid) implements Driver
    {
        public function __construct(private bool $valid) {}

        public function name(): string
        {
            return 'fake';
        }

        public function activate(LicenseRequest $request): VerificationResult
        {
            return $this->valid
                ? VerificationResult::valid(licensedTo: $request->client)
                : VerificationResult::invalid();
        }

        public function verify(?string $key = null): VerificationResult
        {
            return $this->valid ? VerificationResult::valid() : VerificationResult::invalid();
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

beforeEach(function (): void {
    // Centralised event dispatch is the focus here; skip the verify cache so the
    // two verify() calls below do not share a cached result.
    config()->set('license-verifier.cache.enabled', false);
});

function managerWith(Driver $driver): LicenseManager
{
    $manager = Mockery::mock(DriverManager::class);
    $manager->shouldReceive('active')->andReturn($driver);
    $manager->shouldReceive('getDefaultDriver')->andReturn('fake');

    return new LicenseManager($manager, new DomainBinding);
}

it('dispatches activating + activated on successful activation (any driver)', function (): void {
    Event::fake();

    $result = managerWith(fakeDriver(true))->activate(new LicenseRequest('K', client: 'Acme'));

    expect($result->isUsable())->toBeTrue();
    Event::assertDispatched(LicenseActivating::class);
    Event::assertDispatched(LicenseActivated::class, fn ($e): bool => $e->licensedTo === 'Acme');
});

it('dispatches verified / unverified from verify()', function (): void {
    Event::fake();

    managerWith(fakeDriver(true))->verify('K');
    Event::assertDispatched(LicenseVerified::class);

    managerWith(fakeDriver(false))->verify('K');
    Event::assertDispatched(LicenseUnverified::class);
});

it('dispatches deactivated', function (): void {
    Event::fake();

    expect(managerWith(fakeDriver(true))->deactivate('K'))->toBeTrue();
    Event::assertDispatched(LicenseDeactivated::class);
});
