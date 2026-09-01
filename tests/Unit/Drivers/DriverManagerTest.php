<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsOfflineTokens;
use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Licence\Verifier\Drivers\PasetoDriver;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\Capability;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

it('resolves the paseto driver by default', function (): void {
    $manager = app(DriverManager::class);

    expect($manager->getDefaultDriver())->toBe('paseto')
        ->and($manager->active())->toBeInstanceOf(PasetoDriver::class)
        ->and($manager->active()->name())->toBe('paseto');
});

it('declares paseto capabilities', function (): void {
    $driver = app(DriverManager::class)->active();

    expect($driver->capabilities())
        ->toContain(Capability::OfflineTokens->value)
        ->toContain(Capability::Refresh->value)
        ->toContain(Capability::Heartbeat->value)
        ->toContain(Capability::Entitlements->value)
        ->and($driver)->toBeInstanceOf(SupportsOfflineTokens::class);
});

it('exposes an activation field schema for presets', function (): void {
    $fields = app(DriverManager::class)->active()->activationFields();

    expect($fields)->toBeArray()->not->toBeEmpty()
        ->and($fields[0]['name'])->toBe('license_key')
        ->and($fields[0]['required'])->toBeTrue();
});

it('reports unactivated when no license is stored', function (): void {
    $result = app(DriverManager::class)->active()->verify('NOPE');

    expect($result)->toBeInstanceOf(VerificationResult::class)
        ->and($result->valid)->toBeFalse()
        ->and($result->status)->toBe(LicenseStatus::Unactivated);
});

it('lets applications register custom drivers via extend()', function (): void {
    config()->set('license-verifier.default', 'fake');

    $manager = app(DriverManager::class);
    $manager->extend('fake', fn (): Driver => new class implements Driver
    {
        public function name(): string
        {
            return 'fake';
        }

        public function activate(LicenseRequest $request): VerificationResult
        {
            return VerificationResult::valid();
        }

        public function verify(?string $key = null): VerificationResult
        {
            return VerificationResult::valid();
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
    });

    expect($manager->active()->name())->toBe('fake');
});
