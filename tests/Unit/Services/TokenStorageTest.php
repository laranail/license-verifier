<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Services\TokenStorage;

beforeEach(function (): void {
    $this->storage = new TokenStorage;
});

it('stores and retrieves a token', function (): void {
    $token = 'test-token-123';
    $key = 'test-key';

    $this->storage->store($token, $key);
    $retrieved = $this->storage->retrieve($key);

    expect($retrieved)->toBe($token);
});

it('encrypts the token at rest', function (): void {
    $token = 'sensitive-token';
    $key = 'encryption-test';

    $this->storage->store($token, $key);

    // Round-trips through the configured store…
    expect(app(LicenseStore::class)->get($key)['token'])->toBe($token);

    // …and the persisted blob is ciphertext, never the plaintext token.
    $records = rtrim((string) config('license-verifier.storage.path', storage_path('app/licensing')), '/').'/records';
    $raw = collect(File::files($records))->map(fn ($f): string => File::get($f->getPathname()))->implode("\n");
    expect($raw)->not->toContain($token);
});

it('returns null when token does not exist', function (): void {
    $retrieved = $this->storage->retrieve('non-existent');

    expect($retrieved)->toBeNull();
});

it('deletes a stored token', function (): void {
    $token = 'token-to-delete';
    $key = 'delete-test';

    $this->storage->store($token, $key);
    expect($this->storage->exists($key))->toBeTrue();

    $this->storage->delete($key);
    expect($this->storage->exists($key))->toBeFalse();
    expect($this->storage->retrieve($key))->toBeNull();
});

it('checks if token exists', function (): void {
    $token = 'existence-test';
    $key = 'exists-test';

    expect($this->storage->exists($key))->toBeFalse();

    $this->storage->store($token, $key);

    expect($this->storage->exists($key))->toBeTrue();
});

it('stores and retrieves last heartbeat', function (): void {
    $beforeTime = time();

    $this->storage->storeLastHeartbeat();

    $heartbeat = $this->storage->getLastHeartbeat();
    $afterTime = time();

    expect($heartbeat)->toBeGreaterThanOrEqual($beforeTime);
    expect($heartbeat)->toBeLessThanOrEqual($afterTime);
});

it('stores and retrieves grace period data', function (): void {
    $data = [
        'started_at' => now()->toIso8601String(),
        'reason' => 'test-reason',
    ];

    $this->storage->storeGracePeriodData($data);
    $retrieved = $this->storage->getGracePeriodData();

    expect($retrieved)->toBe($data);
});

it('clears all stored data', function (): void {
    // Realistic: one app, one configured license key (+ default + server metadata).
    config(['license-verifier.license_key' => 'key1']);

    $this->storage->store('token1', 'key1');
    $this->storage->store('token2'); // default key
    $this->storage->storeLastHeartbeat();
    $this->storage->storeGracePeriodData(['test' => 'data']);

    $this->storage->clearAll();

    expect($this->storage->retrieve('key1'))->toBeNull();
    expect($this->storage->retrieve())->toBeNull();
    expect($this->storage->getLastHeartbeat())->toBeNull();
    expect($this->storage->getGracePeriodData())->toBeNull();
});

it('stores and retrieves public key bundle', function (): void {
    $bundle = [
        'signing' => ['kid' => 'signing-1', 'public_key' => 'base64key'],
        'root' => ['kid' => 'root-1', 'public_key' => 'base64rootkey'],
        'issued_at' => '2027-01-01T00:00:00+00:00',
    ];

    $this->storage->storePublicKeyBundle($bundle);
    $retrieved = $this->storage->getPublicKeyBundle();

    expect($retrieved)->toBe($bundle);
});

it('returns null when no public key bundle stored', function (): void {
    expect($this->storage->getPublicKeyBundle())->toBeNull();
});

it('stores and retrieves refresh_after', function (): void {
    $refreshAfter = '2027-01-07T00:00:00+00:00';

    $this->storage->storeRefreshAfter($refreshAfter);
    $retrieved = $this->storage->getRefreshAfter();

    expect($retrieved)->toBe($refreshAfter);
});

it('returns null when no refresh_after stored', function (): void {
    expect($this->storage->getRefreshAfter())->toBeNull();
});

it('stores and retrieves entitlements (defaulting to empty)', function (): void {
    expect($this->storage->getEntitlements())->toBe([]);

    $entitlements = ['seats' => 5, 'features' => ['pro', 'api']];
    $this->storage->storeEntitlements($entitlements);

    expect($this->storage->getEntitlements())->toBe($entitlements);
});

it('keeps server-metadata items independent across writes', function (): void {
    // Each writer merges into the one server record without clobbering the others.
    $this->storage->storePublicKeyBundle(['kid' => 'k1']);
    $this->storage->storeEntitlements(['seats' => 3]);
    $this->storage->storeRefreshAfter('2027-01-01T00:00:00+00:00');
    $this->storage->storeLastHeartbeat();

    expect($this->storage->getPublicKeyBundle())->toBe(['kid' => 'k1'])
        ->and($this->storage->getEntitlements())->toBe(['seats' => 3])
        ->and($this->storage->getRefreshAfter())->toBe('2027-01-01T00:00:00+00:00')
        ->and($this->storage->getLastHeartbeat())->toBeInt();
});
