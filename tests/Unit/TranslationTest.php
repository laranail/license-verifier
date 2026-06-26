<?php

declare(strict_types=1);

it('resolves the short license-verifier translation namespace', function (): void {
    $key = 'license-verifier::license-verifier.activated_successfully';

    expect(__($key))
        ->not->toBe($key)                                   // not the raw key
        ->toBe('Your license has been activated successfully.');
});

it('resolves several user-facing keys used by the presets', function (): void {
    foreach (['activation', 'activate', 'deactivate', 'status', 'invalid_key'] as $k) {
        expect(__("license-verifier::license-verifier.{$k}"))
            ->not->toBe("license-verifier::license-verifier.{$k}");
    }
});
