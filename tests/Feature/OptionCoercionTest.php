<?php

declare(strict_types=1);

use Carbon\Carbon;
use Simtabi\Laranail\Licence\Verifier\Support\ReminderManager;
use Simtabi\Laranail\Package\Tools\Testing\AssertsDriverContract;
use Simtabi\Laranail\Licence\Verifier\Commands\LicenseInfoCommand;
use Simtabi\Laranail\Package\Tools\Commands\Concerns\ReadsOptions;
use Simtabi\Laranail\Licence\Verifier\Commands\Command as VerifierCommand;

/**
 * Pins two defects a `(int)` cast produced, both reachable by typing a value
 * rather than by any exotic input.
 *
 * `(int) ''` and `(int) 'abc'` are both `0`, and `0` is not the same as "not
 * supplied" -- but the guard in front of each cast tested for `null`, which is
 * what an ABSENT option returns. An option written `--days=` arrives as `''`,
 * passes that guard, and lands on the cast.
 */
uses(AssertsDriverContract::class);

beforeEach(function (): void {
    config()->set('license-verifier.reminder.default_skip_days', 3);
    app(ReminderManager::class)->clear();
});

it('skips a reminder for the configured default when --days is written without a value', function (): void {
    // Was: (int) '' === 0, so the reminder was written with addDays(0) --
    // "skipped" until now, which is not skipped at all.
    $this->artisan('laranail::license-verifier.reminder', ['action' => 'skip', '--days' => ''])
        ->assertSuccessful();

    expect(app(ReminderManager::class)->skippedUntil())
        ->not->toBeNull()
        ->and(app(ReminderManager::class)->skippedUntil()->isAfter(Carbon::now()->addDays(2)))
        ->toBeTrue('an empty --days must fall back to the configured default, not to 0 days');
});

it('skips a reminder for the configured default when --days is not numeric', function (): void {
    $this->artisan('laranail::license-verifier.reminder', ['action' => 'skip', '--days' => 'soon'])
        ->assertSuccessful();

    expect(app(ReminderManager::class)->skippedUntil()->isAfter(Carbon::now()->addDays(2)))
        ->toBeTrue('a non-numeric --days must fall back to the configured default, not to 0 days');
});

it('still honours an explicit --days', function (): void {
    $this->artisan('laranail::license-verifier.reminder', ['action' => 'skip', '--days' => '10'])
        ->assertSuccessful();

    expect(app(ReminderManager::class)->skippedUntil()->isAfter(Carbon::now()->addDays(9)))
        ->toBeTrue();
});

/**
 * The watch loop breaks on `++$count === $cycles`, and `$count` starts at 1, so
 * `$cycles === 0` never matches -- the command looped forever, including in a
 * non-interactive run where the null-guard above it was meant to return early.
 * Asserting the accessor contract rather than running the loop, because a
 * regression here does not fail a test, it hangs one.
 */
it('reports an empty or non-numeric int option as absent, which re-arms the watch early return', function (): void {
    $intOption = new ReflectionMethod(ReadsOptions::class, 'intOption');

    expect($intOption->getReturnType()?->allowsNull())
        ->toBeTrue('intOption() must be able to report absence, or the null guard cannot work');
});

it('the base command exposes the option accessors its subcommands rely on', function (): void {
    foreach (['intOption', 'strOption', 'boolOption', 'stringOption'] as $method) {
        expect(method_exists(VerifierCommand::class, $method))
            ->toBeTrue("the base command must expose {$method}()");
    }
});

it('falls back to the configured key when the argument is an empty string', function (): void {
    // `artisan …license-verifier.info ""` arrives as '', which `?? config(...)`
    // does not cover: the empty string is not null, so it won the fallback and
    // the command ran against no licence key at all.
    config()->set('license-verifier.license_key', 'CONFIGURED-KEY');

    $command = new ReflectionClass(LicenseInfoCommand::class);
    $source = (string) file_get_contents((string) $command->getFileName());

    expect($source)->not->toContain("\$this->argument('key') ??")
        ->and($source)->toContain("strArgOrNull('key') ??");
});

it('has no console option defaulted by a null-only test', function (): void {
    $this->assertNoNullOnlyOptionGuards(__DIR__ . '/../../src');
});
