<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Licence\Verifier\Facades\LicenceVerifier;
use Simtabi\Laranail\Licence\Verifier\Services\TokenStorage;

it('can complete a full license lifecycle', function (): void {
    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => true,
            'data' => [
                'token' => $this->generateTestToken([
                    'license_id' => 1,
                    'status' => 'active',
                    'max_usages' => 5,
                ]),
                'token_expires_at' => now()->addDays(7)->toIso8601String(),
                'refresh_after' => now()->addDays(6)->toIso8601String(),
                'force_online_after' => now()->addDays(14)->toIso8601String(),
                'license' => ['id' => 'ulid-1', 'status' => 'active', 'max_usages' => 5],
                'usage' => ['id' => 1, 'fingerprint' => 'fp', 'status' => 'active'],
            ],
        ], 200),

        '*/api/licensing/v1/refresh' => Http::response([
            'success' => true,
            'data' => [
                'token' => $this->generateTestToken([
                    'exp' => now()->addYear()->toIso8601String(),
                ]),
                'token_expires_at' => now()->addDays(7)->toIso8601String(),
            ],
        ], 200),

        '*/api/licensing/v1/heartbeat' => Http::response([
            'success' => true,
            'data' => [
                'usage' => ['id' => 1, 'last_seen_at' => now()->toIso8601String()],
            ],
        ], 200),

        '*/api/licensing/v1/deactivate' => Http::response([
            'success' => true,
            'data' => ['message' => 'Usage revoked successfully'],
        ], 200),

        '*/api/licensing/v1/health' => Http::response([
            'success' => true,
            'data' => ['status' => 'healthy', 'checks' => ['database' => ['status' => 'ok']]],
        ], 200),
    ]);

    // Test activation
    $activated = LicenceVerifier::activate('INTEGRATION-TEST-KEY');
    expect($activated->isUsable())->toBeTrue();

    // Test validation
    $isValid = LicenceVerifier::isValid('INTEGRATION-TEST-KEY');
    expect($isValid)->toBeTrue();

    // Test getting license info
    $info = LicenceVerifier::getLicenseInfo('INTEGRATION-TEST-KEY');
    expect($info)->toHaveKey('status');
    expect($info['status'])->toBe('valid'); // normalized LicenseStatus (active → valid)

    // Test refresh
    $refreshed = LicenceVerifier::refresh('INTEGRATION-TEST-KEY');
    expect($refreshed)->toBeTrue();

    // Test heartbeat
    $heartbeat = LicenceVerifier::heartbeat('INTEGRATION-TEST-KEY');
    expect($heartbeat)->toBeTrue();

    // Test server health
    $healthy = LicenceVerifier::isServerHealthy();
    expect($healthy)->toBeTrue();

    // Test deactivation
    $deactivated = LicenceVerifier::deactivate('INTEGRATION-TEST-KEY');
    expect($deactivated)->toBeTrue();

    // Verify license is no longer valid after deactivation
    $isValidAfterDeactivation = LicenceVerifier::isValid('INTEGRATION-TEST-KEY');
    expect($isValidAfterDeactivation)->toBeFalse();
});

it('handles grace period when server is unreachable', function (): void {
    Http::fake([
        '*/api/licensing/v1/*' => Http::response(null, 500),
        '*/api/licensing/v1/health' => Http::response(null, 500),
    ]);

    // Store a valid token first
    $tokenStorage = app(TokenStorage::class);
    $tokenStorage->store($this->generateTestToken(), 'GRACE-TEST-KEY');

    // License should be valid initially
    $isValid = LicenceVerifier::isValid('GRACE-TEST-KEY');
    expect($isValid)->toBeTrue();

    // Server is unreachable
    $healthy = LicenceVerifier::isServerHealthy();
    expect($healthy)->toBeFalse();

    // Start grace period
    LicenceVerifier::startGracePeriod();

    // Should be in grace period
    $inGracePeriod = LicenceVerifier::isInGracePeriod();
    expect($inGracePeriod)->toBeTrue();
});

it('handles expired license correctly', function (): void {
    $expiredToken = $this->generateTestToken([
        'exp' => now()->subDay()->toIso8601String(),
    ]);

    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => true,
            'data' => [
                'token' => $expiredToken,
            ],
        ], 200),
    ]);

    // Activate with expired token
    LicenceVerifier::activate('EXPIRED-KEY');

    // Should not be valid
    $isValid = LicenceVerifier::isValid('EXPIRED-KEY');
    expect($isValid)->toBeFalse();
});

it('detects when license is expiring soon', function (): void {
    $expiringToken = $this->generateTestToken([
        'exp' => now()->addDays(5)->toIso8601String(),
    ]);

    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => true,
            'data' => [
                'token' => $expiringToken,
            ],
        ], 200),
    ]);

    LicenceVerifier::activate('EXPIRING-KEY');

    $isExpiringSoon = LicenceVerifier::isExpiringSoon(7, 'EXPIRING-KEY');
    expect($isExpiringSoon)->toBeTrue();

    $isExpiringSoon = LicenceVerifier::isExpiringSoon(3, 'EXPIRING-KEY');
    expect($isExpiringSoon)->toBeFalse();
});

it('detects when online refresh is required', function (): void {
    $tokenStorage = app(TokenStorage::class);
    $token = $this->generateTestToken([
        'force_online_after' => now()->subDay()->toIso8601String(),
    ]);
    $tokenStorage->store($token, 'FORCE-ONLINE-KEY');

    $requiresRefresh = LicenceVerifier::requiresOnlineRefresh('FORCE-ONLINE-KEY');
    expect($requiresRefresh)->toBeTrue();
});

it('clears all stored data', function (): void {
    config(['license-verifier.license_key' => 'CLEAR-TEST-KEY']);

    $tokenStorage = app(TokenStorage::class);
    $tokenStorage->store($this->generateTestToken(), 'CLEAR-TEST-KEY');

    expect($tokenStorage->exists('CLEAR-TEST-KEY'))->toBeTrue();

    LicenceVerifier::clearAll();

    expect($tokenStorage->exists('CLEAR-TEST-KEY'))->toBeFalse();
});

it('can use middleware to protect routes', function (): void {
    config(['license-verifier.license_key' => 'MIDDLEWARE-TEST-KEY']);

    Http::fake([
        '*/api/licensing/v1/activate' => Http::response([
            'success' => true,
            'data' => [
                'token' => $this->generateTestToken(),
            ],
        ], 200),
    ]);

    LicenceVerifier::activate('MIDDLEWARE-TEST-KEY');

    Route::middleware('license')->get('/protected', fn (): string => 'Protected content');

    $response = $this->get('/protected');
    $response->assertStatus(200);
    $response->assertSee('Protected content');
});

it('blocks access when license is invalid via middleware', function (): void {
    config(['license-verifier.license_key' => 'INVALID-KEY']);

    Http::fake([
        '*/api/licensing/v1/health' => Http::response([
            'success' => true,
            'data' => ['status' => 'healthy'],
        ], 200),
        '*/api/licensing/v1/refresh' => Http::response([
            'success' => false,
            'error' => ['code' => 'INVALID_KEY', 'message' => 'Invalid license'],
        ], 404),
    ]);

    Route::middleware('license')->get('/protected', fn (): string => 'Protected content');

    $response = $this->get('/protected');
    $response->assertStatus(403);
});

it('allows excluded routes without license check', function (): void {
    config(['license-verifier.excluded_routes' => ['public/*']]);

    Route::middleware('license')->get('/public/page', fn (): string => 'Public content');

    $response = $this->get('/public/page');
    $response->assertStatus(200);
    $response->assertSee('Public content');
});
