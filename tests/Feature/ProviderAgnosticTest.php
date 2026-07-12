<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Licence\Verifier\Events\LicenseActivated;
use Simtabi\Laranail\Licence\Verifier\Facades\LicenceVerifier;

/**
 * The headline guarantee: the documented API (facade + middleware) routes through
 * the configured driver — not the hardwired PASETO engine.
 */
it('routes the facade through the active (non-PASETO) driver', function (): void {
    config()->set('license-verifier.default', 'null');

    // NullDriver returns valid without any PASETO token / HTTP call.
    expect(LicenceVerifier::activate('ANY-KEY')->isUsable())->toBeTrue()
        ->and(LicenceVerifier::isValid('ANY-KEY'))->toBeTrue()
        ->and(LicenceVerifier::getLicenseInfo()['status'])->toBe('valid');
});

it('gates middleware through the active driver', function (): void {
    config()->set('license-verifier.default', 'null');

    Route::middleware('license')->get('/protected', fn (): string => 'ok');

    $this->get('/protected')->assertOk()->assertSee('ok');
});

it('uses the configured marketplace driver via the facade (Gumroad, not PASETO)', function (): void {
    config()->set('license-verifier.default', 'gumroad');
    config()->set('license-verifier.drivers.gumroad.base_url', 'https://api.gumroad.com');
    config()->set('license-verifier.drivers.gumroad.product_id', 'prod');
    config()->set('license-verifier.storage.driver', 'database');

    Http::fake(['api.gumroad.com/*' => Http::response([
        'success' => true,
        'purchase' => ['email' => 'buyer@example.com'],
    ])]);

    expect(LicenceVerifier::activate('GR-KEY')->isUsable())->toBeTrue();

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), 'gumroad.com'));
});

it('dispatches lifecycle events for an HTTP driver (not just PASETO)', function (): void {
    Event::fake();

    config()->set('license-verifier.default', 'gumroad');
    config()->set('license-verifier.drivers.gumroad.base_url', 'https://api.gumroad.com');
    config()->set('license-verifier.drivers.gumroad.product_id', 'prod');
    config()->set('license-verifier.storage.driver', 'database');

    Http::fake(['api.gumroad.com/*' => Http::response([
        'success' => true,
        'purchase' => ['email' => 'buyer@example.com'],
    ])]);

    LicenceVerifier::activate('GR-KEY');

    Event::assertDispatched(LicenseActivated::class);
});
