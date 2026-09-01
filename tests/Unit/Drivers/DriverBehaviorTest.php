<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Drivers\CryptolensDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\EasyDigitalDownloadsDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\FreemiusDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\LicenseSpringDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\PaddleDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\UnlockShDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\WooCommerceLicenseManagerDriver;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;

beforeEach(function (): void {
    config()->set('license-verifier.storage.driver', 'database');
});

it('activates a Cryptolens license', function (): void {
    Http::fake(['api.cryptolens.io/*' => Http::response([
        'result' => 0,
        'licenseKey' => ['Expires' => '2027-01-01'],
        'signature' => 'sig',
    ])]);

    $result = new CryptolensDriver(['token' => 't', 'product_id' => '1', 'base_url' => 'https://api.cryptolens.io'])
        ->activate(new LicenseRequest('CL-KEY'));

    expect($result->valid)->toBeTrue()->and($result->expiresAt)->toBe('2027-01-01');
});

it('checks a LicenseSpring license', function (): void {
    Http::fake(['api.licensespring.com/*' => Http::response([
        'license_active' => true,
        'license_enabled' => true,
        'validity_period' => '2027-05-01',
    ])]);

    $result = new LicenseSpringDriver(['api_key' => 'k', 'product' => 'p', 'base_url' => 'https://api.licensespring.com'])
        ->activate(new LicenseRequest('LS-KEY', fingerprint: 'fp'));

    expect($result->valid)->toBeTrue();
});

it('activates a Freemius license', function (): void {
    Http::fake(['api.freemius.com/*' => Http::response([
        'install_id' => 99,
        'install_api_token' => 'tok',
        'expiration' => '2027-09-09',
    ])]);

    $result = new FreemiusDriver(['product_id' => '123', 'base_url' => 'https://api.freemius.com'])
        ->activate(new LicenseRequest('FM-KEY', fingerprint: 'uid'));

    expect($result->valid)->toBeTrue()->and($result->expiresAt)->toBe('2027-09-09');
});

it('activates an EDD license bound to the site URL', function (): void {
    Http::fake(['shop.test/*' => Http::response([
        'success' => true,
        'license' => 'valid',
        'expires' => '2027-03-03',
        'customer_name' => 'Jane',
    ])]);

    $result = new EasyDigitalDownloadsDriver(['store_url' => 'https://shop.test', 'item_id' => '7'])
        ->activate(new LicenseRequest('EDD-KEY'));

    expect($result->valid)->toBeTrue()->and($result->licensedTo)->toBe('Jane');
});

it('activates a WooCommerce License Manager license', function (): void {
    Http::fake(['shop.test/*' => Http::response([
        'success' => true,
        'data' => ['status' => 'active', 'expiresAt' => '2027-04-04'],
    ])]);

    $result = new WooCommerceLicenseManagerDriver([
        'store_url' => 'https://shop.test',
        'consumer_key' => 'ck',
        'consumer_secret' => 'cs',
    ])->activate(new LicenseRequest('WC-KEY'));

    expect($result->valid)->toBeTrue();
});

it('validates a Paddle license', function (): void {
    Http::fake(['api.paddle.com/*' => Http::response(['valid' => true, 'expires_at' => '2027-07-07'])]);

    $result = new PaddleDriver(['api_key' => 'k'])->verify('PD-KEY');

    expect($result->valid)->toBeTrue()->and($result->expiresAt)->toBe('2027-07-07');
});

it('validates an unlock.sh license', function (): void {
    Http::fake(['api.unlock.sh/*' => Http::response(['valid' => true, 'data' => ['expires_at' => '2027-08-08']])]);

    $result = new UnlockShDriver(['api_key' => 'k', 'base_url' => 'https://api.unlock.sh'])
        ->verify('US-KEY');

    expect($result->valid)->toBeTrue();
});
