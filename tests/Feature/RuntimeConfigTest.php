<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\Stores\DatabaseStore;
use Simtabi\Laranail\Licence\Verifier\Stores\FileStore;

it('verifies against a non-default driver at runtime via driver()', function (): void {
    config()->set('license-verifier.default', 'paseto'); // default stays paseto
    config()->set('license-verifier.drivers.gumroad', ['product_id' => 'p', 'base_url' => 'https://api.gumroad.com']);
    config()->set('license-verifier.storage.driver', 'database');
    config()->set('license-verifier.storage.fallback');

    Http::fake(['api.gumroad.com/*' => Http::response(['success' => true, 'purchase' => ['email' => 'b@e.com']])]);

    $result = app(LicenseManager::class)->driver('gumroad')->activate('GR-KEY');

    expect($result->isUsable())->toBeTrue()
        ->and(app(LicenseManager::class)->activeDriver()->name())->toBe('paseto'); // default unchanged
});

it('applies a runtime config override via configure()', function (): void {
    config()->set('license-verifier.default', 'paseto');

    app(LicenseManager::class)->configure(['default' => 'null']);

    expect(app(LicenseManager::class)->activeDriver()->name())->toBe('null')
        ->and(app(LicenseManager::class)->isValid('X'))->toBeTrue(); // null driver = always valid
});

it('reload() rebinds the storage backend after a runtime config change', function (): void {
    config()->set('license-verifier.storage.driver', 'file');
    expect(app(LicenseStore::class))->toBeInstanceOf(FileStore::class);

    config()->set('license-verifier.storage.driver', 'database');
    config()->set('license-verifier.storage.fallback');
    app(LicenseManager::class)->reload();

    expect(app(LicenseStore::class))->toBeInstanceOf(DatabaseStore::class);
});

it('re-reads a per-driver config change at runtime after configure()', function (): void {
    config()->set('license-verifier.default', 'gumroad');
    config()->set('license-verifier.drivers.gumroad', ['product_id' => 'old', 'base_url' => 'https://api.gumroad.com']);
    config()->set('license-verifier.storage.driver', 'database');
    config()->set('license-verifier.storage.fallback');

    Http::fake(['api.gumroad.com/*' => Http::response(['success' => true, 'purchase' => ['email' => 'b@e.com']])]);

    app(LicenseManager::class)->activate('GR-KEY'); // resolves gumroad with product_id=old

    app(LicenseManager::class)->configure(['drivers.gumroad.product_id' => 'new']);
    app(LicenseManager::class)->activate('GR-KEY');

    Http::assertSent(fn ($request): bool => str_contains((string) $request->body(), 'product_id=new'));
});
