<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;

beforeEach(function (): void {
    config()->set('license-verifier.storage.driver', 'database');
    config()->set('cache.default', 'array');
});

it('tries sources in order and the first usable one wins', function (): void {
    Http::fake([
        'api.anystack.sh/*' => Http::response(['message' => 'invalid'], 404),
        'api.whop.com/*' => Http::response(['status' => 'active', 'id' => 'mem_1', 'user' => ['username' => 'ada']]),
    ]);

    $result = app(LicenseManager::class)->activateAcross('KEY-123', ['anystack', 'whop'], 'Ada');

    expect($result->valid)->toBeTrue()
        // the winning source becomes the default for subsequent verify/deactivate.
        ->and(config('license-verifier.default'))->toBe('whop');
});

it('returns the last failure when no source accepts the key', function (): void {
    Http::fake([
        'api.anystack.sh/*' => Http::response(['message' => 'nope'], 404),
        'api.whop.com/*' => Http::response(['status' => 'expired']),
    ]);

    $result = app(LicenseManager::class)->activateAcross('BAD', ['anystack', 'whop']);

    expect($result->valid)->toBeFalse();
});

it('throttles activation when rate limiting is enabled', function (): void {
    config()->set('license-verifier.default', 'whop');
    config()->set('license-verifier.rate_limit.enabled', true);
    config()->set('license-verifier.rate_limit.max_attempts', 1);
    Http::fake(['api.whop.com/*' => Http::response(['status' => 'active', 'id' => 'm', 'user' => ['username' => 'a']])]);

    $manager = app(LicenseManager::class);

    expect($manager->activate('K')->valid)->toBeTrue()
        ->and($manager->activate('K')->valid)->toBeFalse()
        ->and($manager->activate('K')->message)->toContain('Too many');
});

it('writes an audit entry (without the raw key) when auditing is enabled', function (): void {
    config()->set('license-verifier.default', 'whop');
    config()->set('license-verifier.audit.enabled', true);
    Http::fake(['api.whop.com/*' => Http::response(['status' => 'active', 'id' => 'm', 'user' => ['username' => 'a']])]);

    Log::shouldReceive('channel')->andReturnSelf();
    Log::shouldReceive('info')->once()->withArgs(
        fn (string $message, array $context): bool => $message === 'license.activation'
            && isset($context['key_hash'])
            && ! isset($context['key'])
    );

    app(LicenseManager::class)->activate('SECRET-KEY');
});
