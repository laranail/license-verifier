<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseKeyResolver;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Models\LicenseRecord;
use Simtabi\Laranail\Licence\Verifier\Resolvers\ConfigKeyResolver;
use Simtabi\Laranail\Licence\Verifier\Resolvers\ModelKeyResolver;
use Simtabi\Laranail\Licence\Verifier\Stores\CacheStore;
use Simtabi\Laranail\Licence\Verifier\Stores\DatabaseStore;
use Simtabi\Laranail\Licence\Verifier\Stores\FileStore;

dataset('stores', [
    'database' => fn (): DatabaseStore => new DatabaseStore,
    'file' => fn (): FileStore => new FileStore(sys_get_temp_dir().'/lv-store-'.uniqid()),
    'cache' => fn (): CacheStore => new CacheStore('array'),
]);

it('round-trips a license record', function (LicenseStore $store): void {
    expect($store->has('KEY-1'))->toBeFalse()
        ->and($store->get('KEY-1'))->toBeNull();

    $store->put('KEY-1', [
        'key' => 'KEY-1',
        'driver' => 'paseto',
        'licensed_to' => 'Acme Inc',
        'status' => 'active',
    ]);

    expect($store->has('KEY-1'))->toBeTrue()
        ->and($store->get('KEY-1')['licensed_to'])->toBe('Acme Inc');

    $store->forget('KEY-1');

    expect($store->has('KEY-1'))->toBeFalse();
})->with('stores');

it('binds the database store from config', function (): void {
    config()->set('license-verifier.storage.driver', 'database');
    config()->set('license-verifier.storage.fallback'); // bare primary, no decorator

    expect(app(LicenseStore::class))->toBeInstanceOf(DatabaseStore::class);
});

it('defaults to the config key resolver', function (): void {
    config()->set('license-verifier.source', 'config');
    config()->set('license-verifier.license_key', 'CONFIG-KEY');

    $resolver = app(LicenseKeyResolver::class);

    expect($resolver)->toBeInstanceOf(ConfigKeyResolver::class)
        ->and($resolver->resolve())->toBe('CONFIG-KEY');
});

it('resolves the license key from the database model when source=model', function (): void {
    config()->set('license-verifier.source', 'model');

    LicenseRecord::factory()->create(['key' => 'DB-KEY', 'licensed_to' => 'DB Buyer']);

    $resolver = app(LicenseKeyResolver::class);

    expect($resolver)->toBeInstanceOf(ModelKeyResolver::class)
        ->and($resolver->resolve())->toBe('DB-KEY')
        ->and($resolver->details()['licensed_to'])->toBe('DB Buyer');
});

it('lets the license model be swapped via config', function (): void {
    config()->set('license-verifier.models.license', CustomLicenseRecord::class);

    (new DatabaseStore)->put('SWAP', ['key' => 'SWAP', 'status' => 'active']);

    expect(CustomLicenseRecord::query()->where('key', 'SWAP')->exists())->toBeTrue();
});

class CustomLicenseRecord extends LicenseRecord {}
