<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\Services\TokenStorage;
use Simtabi\Laranail\Licence\Verifier\Stores\FallbackLicenseStore;
use Simtabi\Laranail\Licence\Verifier\Stores\FileStore;

/** Re-resolve the LicenseStore after changing storage config. */
function rebindStore(): void
{
    app()->forgetInstance(LicenseStore::class);
}

it('persists the PASETO token + server metadata in the database (encrypted) when storage.driver=database', function (): void {
    config()->set('license-verifier.storage.driver', 'database');
    config()->set('license-verifier.storage.fallback');
    config()->set('license-verifier.license_key', 'LIC-DB');
    rebindStore();

    $storage = app(TokenStorage::class);
    $storage->store('PASETO-DB-TOKEN', 'LIC-DB');
    $storage->storePublicKeyBundle(['signing' => ['kid' => 'sign-xyz']]);

    // Token row + server-metadata row are ciphertext at rest in the DB.
    $token = DB::table('license_verifier_licenses')->where('key', 'LIC-DB')->first();
    $server = DB::table('license_verifier_licenses')->where('key', '__paseto_server__')->first();

    expect($token)->not->toBeNull()
        ->and($token->token)->not->toContain('PASETO-DB-TOKEN')
        ->and($server)->not->toBeNull()
        ->and($server->metadata)->not->toContain('sign-xyz')
        // …and it all decrypts/round-trips through the public API.
        ->and($storage->retrieve('LIC-DB'))->toBe('PASETO-DB-TOKEN')
        ->and($storage->getPublicKeyBundle())->toBe(['signing' => ['kid' => 'sign-xyz']])
        ->and(app(LicenseManager::class)->currentToken())->toBe('PASETO-DB-TOKEN');
});

it('persists the PASETO token in the cache store (encrypted) when storage.driver=cache', function (): void {
    config()->set('license-verifier.storage.driver', 'cache');
    config()->set('license-verifier.storage.fallback');
    config()->set('license-verifier.cache.store', 'array');
    rebindStore();

    app(TokenStorage::class)->store('PASETO-CACHE-TOKEN', 'LIC-CACHE');

    $raw = Cache::store('array')->get('license-verifier:record:'.hash('sha256', 'LIC-CACHE'));

    expect($raw)->toBeString()->not->toContain('PASETO-CACHE-TOKEN')
        ->and(app(TokenStorage::class)->retrieve('LIC-CACHE'))->toBe('PASETO-CACHE-TOKEN');
});

it('wraps a remote primary with a file fallback and mirrors token writes', function (): void {
    config()->set('license-verifier.storage.driver', 'database');
    config()->set('license-verifier.storage.fallback', 'file');
    rebindStore();

    expect(app(LicenseStore::class))->toBeInstanceOf(FallbackLicenseStore::class);

    app(TokenStorage::class)->store('MIRRORED-TOKEN', 'LIC-MIRROR');

    // Present in the DB primary…
    expect(DB::table('license_verifier_licenses')->where('key', 'LIC-MIRROR')->exists())->toBeTrue();

    // …and mirrored to the encrypted local file fallback.
    $records = rtrim((string) config('license-verifier.storage.path'), '/').'/records';
    $blob = collect(File::files($records))->map(fn ($f): string => File::get($f->getPathname()))->implode("\n");

    expect($blob)->not->toBe('')->and($blob)->not->toContain('MIRRORED-TOKEN');
});

it('uses no decorator for the default file driver', function (): void {
    config()->set('license-verifier.storage.driver', 'file');
    rebindStore();

    expect(app(LicenseStore::class))->toBeInstanceOf(FileStore::class);
});
