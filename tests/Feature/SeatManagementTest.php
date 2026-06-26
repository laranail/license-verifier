<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;

beforeEach(function (): void {
    config()->set('license-verifier.default', 'paseto');
    config()->set('license-verifier.license_key', 'SEAT-KEY');
});

it('lists seats through the manager (faked kit)', function (): void {
    Http::fake(['*/api/licensing/v1/usages' => Http::response([
        'success' => true,
        'data' => [
            'usages' => [
                ['id' => 1, 'fingerprint' => 'fp-1', 'last_seen_at' => '2026-01-01T00:00:00Z', 'status' => 'active'],
                ['id' => 2, 'fingerprint' => 'fp-2', 'last_seen_at' => null, 'status' => 'revoked'],
            ],
            'total' => 2,
        ],
    ])]);

    $seats = app(LicenseManager::class)->seats();

    expect($seats)->toHaveCount(2)
        ->and($seats[0]['fingerprint'])->toBe('fp-1');
});

it('revokes a seat through the manager (faked kit)', function (): void {
    Http::fake(['*/api/licensing/v1/usages/revoke' => Http::response([
        'success' => true,
        'data' => ['revoked' => true, 'id' => 1],
    ])]);

    expect(app(LicenseManager::class)->revokeSeat('fp-1'))->toBeTrue();

    Http::assertSent(fn ($r): bool => str_contains((string) $r->url(), 'usages/revoke')
        && $r['target'] === 'fp-1');
});

it('reports seat support for the PASETO driver', function (): void {
    expect(app(LicenseManager::class)->supportsSeatManagement())->toBeTrue();
});

it('runs the seats list command (json)', function (): void {
    Http::fake(['*/api/licensing/v1/usages' => Http::response([
        'success' => true,
        'data' => ['usages' => [['id' => 7, 'fingerprint' => 'fp-xyz', 'status' => 'active']], 'total' => 1],
    ])]);

    Artisan::call('license:seats', ['action' => 'list', '--json' => true]);

    expect(Artisan::output())->toContain('fp-xyz');
});

it('refuses seat management for a driver that does not support it', function (): void {
    config()->set('license-verifier.default', 'null');

    expect(app(LicenseManager::class)->supportsSeatManagement())->toBeFalse();

    Artisan::call('license:seats', ['action' => 'list']);

    expect(Artisan::output())->toContain('does not support seat management');
});
