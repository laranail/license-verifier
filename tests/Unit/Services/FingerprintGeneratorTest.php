<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Verifier\Services\FingerprintGenerator;

it('generates a consistent fingerprint', function (): void {
    $generator = new FingerprintGenerator;

    $fingerprint1 = $generator->generate();
    $fingerprint2 = $generator->generate();

    expect($fingerprint1)->toBe($fingerprint2);
});

it('generates a valid sha256 hash', function (): void {
    $generator = new FingerprintGenerator;

    $fingerprint = $generator->generate();

    expect($fingerprint)->toMatch('/^[a-f0-9]{64}$/');
});

it('returns metadata with expected keys', function (): void {
    $generator = new FingerprintGenerator;

    $metadata = $generator->getMetadata();

    expect($metadata)->toHaveKeys([
        'hostname',
        'os',
        'php_version',
        'laravel_version',
        'environment',
        'timezone',
    ]);
});

it('includes hostname in metadata', function (): void {
    $generator = new FingerprintGenerator;

    $metadata = $generator->getMetadata();

    expect($metadata['hostname'])->not->toBeEmpty();
});

it('includes PHP version in metadata', function (): void {
    $generator = new FingerprintGenerator;

    $metadata = $generator->getMetadata();

    expect($metadata['php_version'])->toBe(PHP_VERSION);
});

it('includes OS family in metadata', function (): void {
    $generator = new FingerprintGenerator;

    $metadata = $generator->getMetadata();

    expect($metadata['os'])->toBe(PHP_OS_FAMILY);
});
