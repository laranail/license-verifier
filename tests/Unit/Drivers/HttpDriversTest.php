<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Drivers\CryptolensDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Licence\Verifier\Drivers\EasyDigitalDownloadsDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\EnvatoDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\FreemiusDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\GenericHttpDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\GumroadDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\KeygenDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\LemonSqueezyDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\LicenseSpringDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\NullDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\PaddleDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\PasetoDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\UnlockShDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\WooCommerceLicenseManagerDriver;
use Simtabi\Laranail\Licence\Verifier\Exceptions\LicensingException;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;

beforeEach(function (): void {
    config()->set('license-verifier.storage.driver', 'database');
});

it('verifies a Gumroad license', function (): void {
    Http::fake(['api.gumroad.com/*' => Http::response([
        'success' => true,
        'uses' => 1,
        'purchase' => ['email' => 'buyer@example.com', 'refunded' => false, 'created_at' => '2026-01-01'],
    ])]);

    $driver = new GumroadDriver(['product_id' => 'p1', 'base_url' => 'https://api.gumroad.com']);
    $result = $driver->activate(new LicenseRequest('GR-KEY'));

    expect($result->valid)->toBeTrue()
        ->and($result->status)->toBe(LicenseStatus::Valid)
        ->and($result->licensedTo)->toBe('buyer@example.com');
});

it('rejects an invalid Gumroad license', function (): void {
    Http::fake(['api.gumroad.com/*' => Http::response(['success' => false, 'message' => 'That license does not exist.'], 404)]);

    $result = new GumroadDriver(['product_id' => 'p1', 'base_url' => 'https://api.gumroad.com'])
        ->verify('BAD');

    expect($result->valid)->toBeFalse();
});

it('activates a Lemon Squeezy license', function (): void {
    Http::fake(['api.lemonsqueezy.com/*' => Http::response([
        'activated' => true,
        'instance' => ['id' => 'inst_1'],
        'license_key' => ['status' => 'active', 'expires_at' => '2027-01-01'],
    ])]);

    $result = new LemonSqueezyDriver(['base_url' => 'https://api.lemonsqueezy.com'])
        ->activate(new LicenseRequest('LS-KEY', client: 'my-app'));

    expect($result->valid)->toBeTrue()->and($result->expiresAt)->toBe('2027-01-01');
});

it('validates a Keygen license key', function (): void {
    Http::fake(['api.keygen.sh/*' => Http::response([
        'meta' => ['valid' => true, 'detail' => 'is valid', 'code' => 'VALID'],
        'data' => ['attributes' => ['expiry' => '2027-06-01', 'metadata' => []]],
    ])]);

    $result = new KeygenDriver(['account' => 'acme', 'base_url' => 'https://api.keygen.sh'])
        ->activate(new LicenseRequest('KG-KEY'));

    expect($result->valid)->toBeTrue()->and($result->expiresAt)->toBe('2027-06-01');
});

it('activates an Envato/Botble-style purchase code and binds the domain', function (): void {
    Http::fake(['license.test/*' => Http::response(['status' => true, 'lic_response' => 'LICENSE-FILE-BLOB'])]);

    $driver = new EnvatoDriver([
        'server_url' => 'https://license.test',
        'api_key' => 'k',
        'product_id' => 'prod',
    ]);

    $result = $driver->activate(new LicenseRequest('PURCHASE-CODE', client: 'john'));

    expect($result->valid)->toBeTrue()
        ->and($result->licensedTo)->toBe('john')
        // boundDomains reads the stored activation host for that license key.
        ->and($driver->boundDomains('PURCHASE-CODE'))->not->toBeEmpty();
});

it('maps a bespoke service via the generic driver', function (): void {
    Http::fake(['custom.test/*' => Http::response(['meta' => ['valid' => true], 'data' => ['status' => 'active', 'expires_at' => '2028-01-01']])]);

    $driver = new GenericHttpDriver([
        'base_url' => 'https://custom.test',
        'endpoints' => ['validate' => ['method' => 'POST', 'path' => '/check']],
        'response_map' => ['valid' => 'meta.valid', 'status' => 'data.status', 'expires_at' => 'data.expires_at'],
    ]);

    $result = $driver->verify('ANY');

    expect($result->valid)->toBeTrue()->and($result->expiresAt)->toBe('2028-01-01');
});

it('resolves built-in drivers through the manager', function (): void {
    config()->set('license-verifier.default', 'gumroad');
    config()->set('license-verifier.drivers.gumroad.base_url', 'https://api.gumroad.com');

    expect(app(DriverManager::class)->active())->toBeInstanceOf(GumroadDriver::class);
});

it('resolves every registered driver by name', function (string $name, string $class): void {
    config()->set('license-verifier.default', $name);

    expect(app(DriverManager::class)->active())->toBeInstanceOf($class);
})->with([
    ['paseto', PasetoDriver::class],
    ['envato', EnvatoDriver::class],
    ['keygen', KeygenDriver::class],
    ['lemonsqueezy', LemonSqueezyDriver::class],
    ['gumroad', GumroadDriver::class],
    ['cryptolens', CryptolensDriver::class],
    ['licensespring', LicenseSpringDriver::class],
    ['freemius', FreemiusDriver::class],
    ['edd', EasyDigitalDownloadsDriver::class],
    ['woocommerce', WooCommerceLicenseManagerDriver::class],
    ['paddle', PaddleDriver::class],
    ['unlocksh', UnlockShDriver::class],
    ['generic', GenericHttpDriver::class],
    ['null', NullDriver::class],
]);

it('allows the null driver outside production and refuses it in production', function (): void {
    expect(new NullDriver)->toBeInstanceOf(NullDriver::class);

    app()->detectEnvironment(fn (): string => 'production');
    expect(fn (): NullDriver => new NullDriver([]))
        ->toThrow(LicensingException::class);
});
