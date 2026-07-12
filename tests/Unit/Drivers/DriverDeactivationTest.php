<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Drivers\CryptolensDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\FreemiusDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\LicenseSpringDriver;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;

it('releases the Cryptolens machine on deactivate, then forgets locally', function (): void {
    Http::fake(['api.cryptolens.io/*' => Http::response(['result' => 0])]);

    $driver = new CryptolensDriver(['token' => 't', 'product_id' => '1', 'base_url' => 'https://api.cryptolens.io']);

    expect($driver->deactivate('CL-KEY'))->toBeTrue();
    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/api/key/Deactivate'));
});

it('calls the LicenseSpring deactivate endpoint, then forgets locally', function (): void {
    Http::fake(['api.licensespring.com/*' => Http::response(['deactivated' => true])]);

    $driver = new LicenseSpringDriver(['api_key' => 'k', 'shared_key' => 's', 'product' => 'p', 'base_url' => 'https://api.licensespring.com']);

    expect($driver->deactivate('LS-KEY'))->toBeTrue();
    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/api/v4/deactivate_license'));
});

it('deletes the Freemius install on deactivate, then forgets locally', function (): void {
    Http::fake(['api.freemius.com/*' => Http::response(['install_id' => 77, 'expiration' => '2030-01-01'])]);

    $driver = new FreemiusDriver(['product_id' => '123', 'secret_key' => 'sk', 'base_url' => 'https://api.freemius.com']);
    $driver->activate(new LicenseRequest('FM-KEY')); // stores install_id = 77

    expect($driver->deactivate('FM-KEY'))->toBeTrue();
    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/installs/77.json')
        && $request->method() === 'DELETE');
});

it('still succeeds and forgets locally when the provider deactivate call fails', function (): void {
    Http::fake(['api.cryptolens.io/*' => fn () => throw new ConnectionException('down')]);

    $driver = new CryptolensDriver(['token' => 't', 'product_id' => '1', 'base_url' => 'https://api.cryptolens.io']);

    expect($driver->deactivate('CL-KEY'))->toBeTrue(); // best-effort: never throws
});
