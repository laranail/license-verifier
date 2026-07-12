<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Verifier\Exceptions\LicensingException;
use Simtabi\Laranail\Licence\Verifier\LicenceVerifier;
use Simtabi\Laranail\Licence\Verifier\Services\FingerprintGenerator;
use Simtabi\Laranail\Licence\Verifier\Services\LicensingApiClient;
use Simtabi\Laranail\Licence\Verifier\Services\TokenStorage;
use Simtabi\Laranail\Licence\Verifier\Services\TokenValidator;

beforeEach(function (): void {
    $this->fingerprintGenerator = Mockery::mock(FingerprintGenerator::class);
    $this->apiClient = Mockery::mock(LicensingApiClient::class);
    $this->tokenStorage = Mockery::mock(TokenStorage::class);
    $this->tokenValidator = Mockery::mock(TokenValidator::class);

    $this->client = new LicenceVerifier(
        $this->fingerprintGenerator,
        $this->apiClient,
        $this->tokenStorage,
        $this->tokenValidator
    );

    $this->fingerprintGenerator->shouldReceive('generate')
        ->andReturn('test-fingerprint')
        ->byDefault();
});

it('activates a license successfully', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->fingerprintGenerator->shouldReceive('getMetadata')
        ->once()
        ->andReturn(['meta' => 'data']);

    $this->apiClient->shouldReceive('activate')
        ->with('TEST-KEY', 'test-fingerprint', ['meta' => 'data'])
        ->once()
        ->andReturn([
            'success' => true,
            'data' => [
                'token' => 'activated-token',
                'refresh_after' => '2027-01-07T00:00:00+00:00',
                'public_key_bundle' => ['signing' => ['kid' => 'k1']],
            ],
        ]);

    $this->tokenStorage->shouldReceive('store')
        ->with('activated-token', 'TEST-KEY')
        ->once();

    $this->tokenStorage->shouldReceive('storeLastHeartbeat')
        ->once();

    $this->tokenStorage->shouldReceive('storePublicKeyBundle')
        ->with(['signing' => ['kid' => 'k1']])
        ->once();

    $this->tokenStorage->shouldReceive('storeRefreshAfter')
        ->with('2027-01-07T00:00:00+00:00')
        ->once();

    $result = $this->client->activate();

    expect($result)->toBeTrue();
});

it('throws exception when no license key provided for activation', function (): void {
    config(['license-verifier.license_key' => null]);

    $this->client->activate();
})->throws(LicensingException::class, 'No license key provided');

it('deactivates a license successfully', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->apiClient->shouldReceive('deactivate')
        ->with('TEST-KEY', 'test-fingerprint', null)
        ->once();

    $this->tokenStorage->shouldReceive('delete')
        ->with('TEST-KEY')
        ->once();

    $result = $this->client->deactivate();

    expect($result)->toBeTrue();
});

it('checks if license is valid', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn('valid-token');

    $this->tokenValidator->shouldReceive('isValid')
        ->with('valid-token')
        ->once()
        ->andReturn(true);

    $result = $this->client->isValid();

    expect($result)->toBeTrue();
});

it('returns false when no token stored', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(null);

    $result = $this->client->isValid();

    expect($result)->toBeFalse();
});

it('validates license with exception on failure', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn('valid-token');

    $this->tokenValidator->shouldReceive('validate')
        ->with('valid-token')
        ->once()
        ->andReturn(['license_id' => 1, 'status' => 'active']);

    $result = $this->client->validate();

    expect($result)->toBe(['license_id' => 1, 'status' => 'active']);
});

it('throws exception when validating without stored token', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(null);

    $this->client->validate();
})->throws(LicensingException::class, 'The license has not been activated.');

it('refreshes a license token', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->apiClient->shouldReceive('refresh')
        ->with('TEST-KEY', 'test-fingerprint')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => ['token' => 'refreshed-token'],
        ]);

    $this->tokenStorage->shouldReceive('store')
        ->with('refreshed-token', 'TEST-KEY')
        ->once();

    $this->tokenStorage->shouldReceive('storeLastHeartbeat')
        ->once();

    $result = $this->client->refresh();

    expect($result)->toBeTrue();
});

it('sends heartbeat when enabled', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);
    config(['license-verifier.heartbeat.enabled' => true]);

    $this->tokenStorage->shouldReceive('getLastHeartbeat')
        ->once()
        ->andReturn(null);

    $this->apiClient->shouldReceive('heartbeat')
        ->once()
        ->andReturn(['success' => true]);

    $this->tokenStorage->shouldReceive('storeLastHeartbeat')
        ->once();

    $result = $this->client->heartbeat();

    expect($result)->toBeTrue();
});

it('skips heartbeat when disabled', function (): void {
    config(['license-verifier.heartbeat.enabled' => false]);

    $this->apiClient->shouldNotReceive('heartbeat');

    $result = $this->client->heartbeat();

    expect($result)->toBeTrue();
});

it('gets license information', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn('valid-token');

    $licenseInfo = [
        'license_id' => 1,
        'status' => 'active',
        'expires_at' => now()->addYear()->toIso8601String(),
    ];

    $this->tokenValidator->shouldReceive('extractLicenseInfo')
        ->with('valid-token')
        ->once()
        ->andReturn($licenseInfo);

    $result = $this->client->getLicenseInfo();

    expect($result)->toBe($licenseInfo);
});

it('checks if license is expiring soon', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn('valid-token');

    $this->tokenValidator->shouldReceive('isExpiringSoon')
        ->with('valid-token', 7)
        ->once()
        ->andReturn(true);

    $result = $this->client->isExpiringSoon(7);

    expect($result)->toBeTrue();
});

it('checks if online refresh is required', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn('valid-token');

    $this->tokenValidator->shouldReceive('requiresOnlineRefresh')
        ->with('valid-token')
        ->once()
        ->andReturn(true);

    $result = $this->client->requiresOnlineRefresh();

    expect($result)->toBeTrue();
});

it('clears all stored data', function (): void {
    $this->tokenStorage->shouldReceive('clearAll')
        ->once();

    $this->client->clearAll();
});

it('checks if in grace period', function (): void {
    $this->tokenStorage->shouldReceive('getGracePeriodData')
        ->once()
        ->andReturn([
            'started_at' => now()->subDay()->toIso8601String(),
        ]);

    config(['license-verifier.grace_period_days' => 7]);

    $result = $this->client->isInGracePeriod();

    expect($result)->toBeTrue();
});

it('starts grace period', function (): void {
    $this->tokenStorage->shouldReceive('storeGracePeriodData')
        ->once()
        ->with(Mockery::on(fn ($data): bool => isset($data['started_at']) && isset($data['reason'])));

    $this->client->startGracePeriod();
});

it('checks server health', function (): void {
    $this->apiClient->shouldReceive('health')
        ->once()
        ->andReturn(true);

    $result = $this->client->isServerHealthy();

    expect($result)->toBeTrue();
});

it('checks if should refresh proactively when refresh_after is past', function (): void {
    $this->tokenStorage->shouldReceive('getRefreshAfter')
        ->once()
        ->andReturn(now()->subHour()->toIso8601String());

    expect($this->client->shouldRefreshProactively())->toBeTrue();
});

it('checks if should not refresh proactively when refresh_after is future', function (): void {
    $this->tokenStorage->shouldReceive('getRefreshAfter')
        ->once()
        ->andReturn(now()->addDay()->toIso8601String());

    expect($this->client->shouldRefreshProactively())->toBeFalse();
});

it('returns false for requiresOnlineRefresh when no token', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(null);

    expect($this->client->requiresOnlineRefresh())->toBeFalse();
});

it('returns false for requiresOnlineRefresh when no license key', function (): void {
    config(['license-verifier.license_key' => null]);

    expect($this->client->requiresOnlineRefresh())->toBeFalse();
});

it('returns false for shouldRefreshProactively when no refresh_after', function (): void {
    $this->tokenStorage->shouldReceive('getRefreshAfter')
        ->once()
        ->andReturn(null);

    expect($this->client->shouldRefreshProactively())->toBeFalse();
});

it('returns false for isExpiringSoon when no license key', function (): void {
    config(['license-verifier.license_key' => null]);

    expect($this->client->isExpiringSoon())->toBeFalse();
});

it('returns false for isExpiringSoon when no token', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->tokenStorage->shouldReceive('retrieve')
        ->with('TEST-KEY')
        ->once()
        ->andReturn(null);

    expect($this->client->isExpiringSoon())->toBeFalse();
});

it('returns empty array for getLicenseInfo when no license key', function (): void {
    config(['license-verifier.license_key' => null]);

    expect($this->client->getLicenseInfo())->toBe([]);
});

it('returns false for deactivate when no license key', function (): void {
    config(['license-verifier.license_key' => null]);

    expect($this->client->deactivate())->toBeFalse();
});

it('returns false for refresh when no license key', function (): void {
    config(['license-verifier.license_key' => null]);

    expect($this->client->refresh())->toBeFalse();
});

it('returns false for heartbeat when no license key', function (): void {
    config(['license-verifier.heartbeat.enabled' => true]);
    config(['license-verifier.license_key' => null]);

    expect($this->client->heartbeat())->toBeFalse();
});

it('returns false for isInGracePeriod when no grace data', function (): void {
    $this->tokenStorage->shouldReceive('getGracePeriodData')
        ->once()
        ->andReturn(null);

    expect($this->client->isInGracePeriod())->toBeFalse();
});

it('initializes from stored bundle on boot', function (): void {
    $bundle = [
        'signing' => ['public_key' => 'signing-key-base64'],
        'root' => ['public_key' => 'root-key-base64'],
    ];

    $this->tokenStorage->shouldReceive('getPublicKeyBundle')
        ->once()
        ->andReturn($bundle);

    $this->tokenValidator->shouldReceive('updatePublicKey')
        ->with('signing-key-base64')
        ->once();

    $this->tokenValidator->shouldReceive('setRootPublicKey')
        ->with('root-key-base64')
        ->once();

    $this->client->initializeFromStoredBundle();
});

it('skips initialization when no stored bundle exists', function (): void {
    $this->tokenStorage->shouldReceive('getPublicKeyBundle')
        ->once()
        ->andReturn(null);

    $this->tokenValidator->shouldNotReceive('updatePublicKey');
    $this->tokenValidator->shouldNotReceive('setRootPublicKey');

    $this->client->initializeFromStoredBundle();
});

it('applies public key bundle when activating', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $bundle = [
        'signing' => ['public_key' => 'new-signing-key'],
        'root' => ['public_key' => 'new-root-key'],
    ];

    $this->fingerprintGenerator->shouldReceive('getMetadata')
        ->once()
        ->andReturn([]);

    $this->apiClient->shouldReceive('activate')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => [
                'token' => 'test-token',
                'public_key_bundle' => $bundle,
            ],
        ]);

    $this->tokenStorage->shouldReceive('store')->once();
    $this->tokenStorage->shouldReceive('storeLastHeartbeat')->once();
    $this->tokenStorage->shouldReceive('storePublicKeyBundle')->with($bundle)->once();

    $this->tokenValidator->shouldReceive('updatePublicKey')
        ->with('new-signing-key')
        ->once();

    $this->tokenValidator->shouldReceive('setRootPublicKey')
        ->with('new-root-key')
        ->once();

    $result = $this->client->activate();

    expect($result)->toBeTrue();
});

it('returns stored entitlements', function (): void {
    $this->tokenStorage->shouldReceive('getEntitlements')
        ->once()
        ->andReturn(['feature_a', 'feature_b']);

    $result = $this->client->getEntitlements();

    expect($result)->toBe(['feature_a', 'feature_b']);
});

it('stores entitlements from server response', function (): void {
    config(['license-verifier.license_key' => 'TEST-KEY']);

    $this->fingerprintGenerator->shouldReceive('getMetadata')
        ->once()
        ->andReturn([]);

    $this->apiClient->shouldReceive('activate')
        ->once()
        ->andReturn([
            'success' => true,
            'data' => [
                'token' => 'test-token',
                'license' => [
                    'entitlements' => ['api_access', 'premium'],
                ],
            ],
        ]);

    $this->tokenStorage->shouldReceive('store')->once();
    $this->tokenStorage->shouldReceive('storeLastHeartbeat')->once();
    $this->tokenStorage->shouldReceive('storeEntitlements')
        ->with(['api_access', 'premium'])
        ->once();

    $result = $this->client->activate();

    expect($result)->toBeTrue();
});
