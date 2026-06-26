<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Simtabi\Laranail\Licence\Verifier\Support\ReminderManager;

it('exposes namespaced commands with license:* aliases', function (): void {
    $names = array_keys($this->app[Kernel::class]->all());

    expect($names)->toContain('laranail::license-verifier.status')
        ->toContain('laranail::license-verifier.drivers')
        ->toContain('laranail::license-verifier.manage');
});

it('status returns a failing exit code when unactivated', function (): void {
    $this->artisan('license:status')->assertExitCode(1);
});

it('status emits json when asked', function (): void {
    $code = Artisan::call('license:status', ['--json' => true]);

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('"status"');
});

it('lists drivers with capabilities', function (): void {
    Artisan::call('license:drivers');

    expect(Artisan::output())->toContain('paseto');
});

it('prints the device fingerprint as json', function (): void {
    $code = Artisan::call('license:fingerprint', ['--json' => true]);

    expect($code)->toBe(0)
        ->and(Artisan::output())->toContain('"fingerprint"');
});

it('skips and clears the reminder via the command', function (): void {
    config()->set('license-verifier.storage.path', sys_get_temp_dir().'/lv-cli-reminder-'.uniqid());

    $this->artisan('license:reminder skip --days=2')->assertSuccessful();
    expect(app(ReminderManager::class)->isSkipped())->toBeTrue();

    $this->artisan('license:reminder clear')->assertSuccessful();
    expect(app(ReminderManager::class)->isSkipped())->toBeFalse();
});

it('runs doctor diagnostics', function (): void {
    $this->artisan('license:doctor')->assertExitCode(0);
});
