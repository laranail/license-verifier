<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Licence\Verifier\Services\TokenStorage;

it('shows the configured source', function (): void {
    config()->set('license-verifier.source', 'config');
    config()->set('license-verifier.license_key', 'ABCD-1234-EFGH-5678');

    Artisan::call('license:source', ['--json' => true]);

    expect(Artisan::output())->toContain('"source"');
});

it('inspects a driver and its capabilities', function (): void {
    Artisan::call('license:driver', ['name' => 'paseto', '--json' => true]);

    expect(Artisan::output())->toContain('"capabilities"');
});

it('lists drivers with capabilities (json content)', function (): void {
    Artisan::call('license:drivers', ['--json' => true]);

    expect(Artisan::output())
        ->toContain('paseto')
        ->toContain('"capabilities"');
});

it('reports doctor diagnostics (json content)', function (): void {
    Artisan::call('license:doctor', ['--json' => true]);

    expect(Artisan::output())
        ->toContain('Driver resolves')
        ->toContain('Sodium extension');
});

it('clears stored license data with --force', function (): void {
    $this->artisan('license:clear --force')->assertSuccessful();
});

it('runs the watch dashboard for a fixed number of cycles', function (): void {
    $this->artisan('license:watch --cycles=1 --interval=1')->assertSuccessful();
});

it('exports and re-imports the offline token (air-gap round-trip)', function (): void {
    config()->set('license-verifier.source', 'config');
    config()->set('license-verifier.license_key', 'OFFLINE-KEY');

    /** @var TokenStorage $storage */
    $storage = app(TokenStorage::class);
    $storage->store('THE-OFFLINE-TOKEN', 'OFFLINE-KEY');

    $path = sys_get_temp_dir().'/lv-token-'.uniqid().'.token';

    $this->artisan("license:token export {$path}")->assertSuccessful();
    expect(File::exists($path))->toBeTrue();

    // Wipe and re-import.
    $storage->delete('OFFLINE-KEY');
    expect($storage->retrieve('OFFLINE-KEY'))->toBeNull();

    $this->artisan("license:token import {$path}")->assertSuccessful();
    expect($storage->retrieve('OFFLINE-KEY'))->toBe('THE-OFFLINE-TOKEN');

    File::delete($path);
});
