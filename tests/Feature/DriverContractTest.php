<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;

/**
 * Asserts that every driver this package's config offers is one the manager can actually build.
 *
 * `DriverManager extends Illuminate\Support\Manager`, which resolves a driver by **interpolating its
 * name into a method name** — `driver('keygen')` becomes `createKeygenDriver()`. With sixteen
 * drivers the two lists are far apart in the file and drift silently: adding a `drivers.foo` block
 * is a config change nobody executes, and the manager only objects when someone verifies a licence
 * through it, at which point it is an `InvalidArgumentException` in production.
 *
 * Mocking the manager cannot catch this, because a mock replaces the very resolution being tested.
 * `laranail/authkit-social` shipped two providers that could not authenticate at all behind a fully
 * green suite for exactly that reason.
 *
 * No network: the assertion is that a driver is *constructible*, not that a licence server answers.
 */
function licenseVerifierDriverKeys(): array
{
    $drivers = config('license-verifier.drivers', []);

    return is_array($drivers) ? array_keys($drivers) : [];
}

it('offers drivers in its config, so this guard is not vacuous', function (): void {
    // A discovery-driven test that finds nothing passes everything below it trivially.
    expect(licenseVerifierDriverKeys())->not->toBeEmpty();
});

it('has a create method for every driver the config offers', function (): void {
    $manager  = new ReflectionClass(DriverManager::class);
    $offenders = [];

    foreach (licenseVerifierDriverKeys() as $key) {
        // Manager studlies the name: 'lemon-squeezy' would become createLemonSqueezyDriver.
        $method = 'create' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', (string) $key))) . 'Driver';

        if (! $manager->hasMethod($method)) {
            $offenders[] = "{$key} => {$method}()";
        }
    }

    expect($offenders)->toBe([], sprintf(
        "the config offers these drivers but DriverManager cannot build them:\n  %s",
        implode("\n  ", $offenders),
    ));
});

it('resolves the configured default', function (): void {
    // The default is the one name most likely to be wrong in a deployment and least likely to be
    // exercised before the first real verification.
    $default = config('license-verifier.default');

    expect($default)->toBeString()->not->toBeEmpty()
        ->and(licenseVerifierDriverKeys())->toContain($default);
});

it('fails loudly, not silently, on a driver that does not exist', function (): void {
    // Pinning the failure mode: an exception at resolution, not a null that surfaces later as a
    // licence check that quietly never ran.
    expect(fn () => app(DriverManager::class)->driver('not-a-real-driver'))
        ->toThrow(InvalidArgumentException::class);
});
