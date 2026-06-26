<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;

/**
 * Proves driver-level domain binding actually enforces: a license activated for
 * host A is rejected when the app later runs on host B.
 */
function manager(): LicenseManager
{
    app()->forgetInstance(DriverManager::class);

    return app(LicenseManager::class);
}

it('binds an EDD license to the host it was activated on', function (): void {
    config()->set('license-verifier.default', 'edd');
    config()->set('license-verifier.drivers.edd', ['store_url' => 'https://shop.test', 'item_id' => '1']);
    config()->set('license-verifier.license_key', 'EDD-KEY');
    config()->set('license-verifier.bindings.domain.enabled', true);
    config()->set('license-verifier.cache.enabled', false); // test the pure enforcement path

    Http::fake(['shop.test/*' => Http::response(['success' => true, 'license' => 'valid', 'expires' => '2030-01-01'])]);

    URL::forceRootUrl('https://site-a.example');
    manager()->activate('EDD-KEY'); // stores domain = https://site-a.example

    // Same host → usable.
    expect(manager()->verify('EDD-KEY')->isUsable())->toBeTrue();

    // Moved to a different host → downgraded to Invalid (binding enforces).
    URL::forceRootUrl('https://site-b.example');
    expect(manager()->verify('EDD-KEY')->status)->toBe(LicenseStatus::Invalid);
})->after(fn () => app()->forgetInstance(LicenseStore::class));
