<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\Services\TokenStorage;
use Simtabi\Laranail\Licence\Verifier\Stores\CacheStore;
use Simtabi\Laranail\Licence\Verifier\Stores\DatabaseStore;
use Simtabi\Laranail\Licence\Verifier\Stores\FileStore;

it('stores the token encrypted at rest in the database store', function (): void {
    (new DatabaseStore)->put('K', [
        'key' => 'K',
        'token' => 'SECRET-TOKEN',
        'metadata' => ['hidden' => 'SECRET-META'],
    ]);

    $raw = DB::table('license_verifier_licenses')->where('key', 'K')->first();

    // Token + metadata columns are ciphertext, not the plaintext.
    expect($raw->token)->not->toContain('SECRET-TOKEN')
        ->and($raw->metadata)->not->toContain('SECRET-META')
        // …but the store decrypts transparently on read.
        ->and((new DatabaseStore)->get('K')['token'])->toBe('SECRET-TOKEN');
});

it('encrypts cache-store payloads at rest', function (): void {
    new CacheStore('array')->put('K', ['token' => 'SECRET']);

    $raw = Cache::store('array')->get('license-verifier:record:'.hash('sha256', 'K'));

    expect($raw)->toBeString()->not->toContain('SECRET')
        ->and(new CacheStore('array')->get('K')['token'])->toBe('SECRET');
});

it('exposes the active token through currentToken (PASETO)', function (): void {
    config()->set('license-verifier.default', 'paseto');
    config()->set('license-verifier.license_key', 'TK');

    app(TokenStorage::class)->store('THE-OFFLINE-TOKEN', 'TK');

    expect(app(LicenseManager::class)->currentToken())->toBe('THE-OFFLINE-TOKEN');
});

it('writes FileStore records atomically with owner-only permissions', function (): void {
    $path = sys_get_temp_dir().'/lv-fs-'.uniqid();
    $store = new FileStore($path);

    $store->put('K', ['token' => 'SECRET']);

    $file = $path.'/records/'.hash('sha256', 'K').'.json';

    expect(File::exists($file))->toBeTrue()
        ->and(substr(sprintf('%o', fileperms($file)), -4))->toBe('0600') // owner-only
        ->and(File::get($file))->not->toContain('SECRET')                 // ciphertext
        ->and($store->get('K')['token'])->toBe('SECRET')                  // round-trips
        // no leftover temp files from the atomic write
        ->and(collect(File::files($path.'/records'))->filter(fn ($f): bool => str_contains($f->getFilename(), '.tmp')))->toBeEmpty();

    File::deleteDirectory($path);
});

it('treats a non-positive cache TTL as forever (never immediate-expiry)', function (): void {
    $store = new CacheStore('array', 0);

    $store->put('K', ['token' => 'PERSISTS']);

    // ttl 0 must not evict immediately.
    expect($store->get('K'))->toBe(['token' => 'PERSISTS']);
});
