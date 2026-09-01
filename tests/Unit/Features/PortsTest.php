<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Bindings\DomainBinding;
use Simtabi\Laranail\Licence\Verifier\Support\ReminderManager;
use Simtabi\Laranail\Licence\Verifier\Support\ThirdPartyIpResolver;

it('skips and clears the license reminder', function (): void {
    config()->set('license-verifier.storage.path', sys_get_temp_dir().'/lv-reminder-'.uniqid());

    $reminder = new ReminderManager;

    expect($reminder->isSkipped())->toBeFalse();

    $reminder->skip(3);

    expect($reminder->isSkipped())->toBeTrue()
        ->and($reminder->skippedUntil()->isFuture())->toBeTrue();

    $reminder->clear();

    expect($reminder->isSkipped())->toBeFalse();
});

it('treats an elapsed reminder skip as not skipped', function (): void {
    config()->set('license-verifier.storage.path', sys_get_temp_dir().'/lv-reminder-'.uniqid());

    $reminder = new ReminderManager;
    $reminder->skip(-1); // already in the past

    expect($reminder->isSkipped())->toBeFalse();
});

it('resolves a configured static IP without a network call', function (): void {
    config()->set('license-verifier.ip.static_ip', '203.0.113.5');
    Http::fake(); // any network call would be a failure of intent

    expect((new ThirdPartyIpResolver)->resolve())->toBe('203.0.113.5');
});

it('resolves the IP from the lookup service when no static IP is set', function (): void {
    config()->set('license-verifier.ip.static_ip');
    config()->set('license-verifier.ip.lookup_url', 'https://ip.test/plain');
    Http::fake(['ip.test/*' => Http::response('198.51.100.7')]);

    expect((new ThirdPartyIpResolver)->resolve())->toBe('198.51.100.7');
});

it('enforces domain binding against an allowlist', function (): void {
    config()->set('license-verifier.bindings.domain.enabled', true);

    $binding = new DomainBinding;

    config()->set('license-verifier.bindings.domain.allowed', ['example.com']);
    expect($binding->passes('app.example.com'))->toBeTrue()   // subdomain of example.com
        ->and($binding->passes('example.com'))->toBeTrue()
        ->and($binding->passes('evil.test'))->toBeFalse();

    config()->set('license-verifier.bindings.domain.allowed', ['other.com']);
    expect($binding->fails('app.example.com'))->toBeTrue();
});

it('passes domain binding when disabled', function (): void {
    config()->set('license-verifier.bindings.domain.enabled', false);
    config()->set('license-verifier.bindings.domain.allowed', ['nope.com']);

    expect((new DomainBinding)->passes('anything.test'))->toBeTrue();
});
