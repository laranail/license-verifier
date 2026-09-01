<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsDomainBinding;
use Simtabi\Laranail\Licence\Verifier\Drivers\AnystackDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Licence\Verifier\Drivers\WhopDriver;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;

beforeEach(function (): void {
    config()->set('license-verifier.storage.driver', 'database');
});

it('activates an active Whop membership', function (): void {
    Http::fake(['api.whop.com/*' => Http::response(['status' => 'active', 'id' => 'mem_1', 'user' => ['username' => 'ada']])]);

    $result = new WhopDriver(['api_key' => 'k', 'base_url' => 'https://api.whop.com'])
        ->activate(new LicenseRequest('mem_1', client: 'Ada'));

    expect($result->valid)->toBeTrue()
        ->and($result->status)->toBe(LicenseStatus::Valid)
        ->and($result->licensedTo)->toBe('ada');
});

it('rejects an inactive Whop membership', function (): void {
    Http::fake(['api.whop.com/*' => Http::response(['status' => 'expired'])]);

    expect(new WhopDriver(['base_url' => 'https://api.whop.com'])->verify('mem_x')->valid)->toBeFalse();
});

it('activates an Anystack license and binds the host', function (): void {
    Http::fake(['api.anystack.sh/*' => Http::response(['data' => ['id' => 'act_1', 'license_id' => 'lic_1']])]);

    $driver = new AnystackDriver(['product_id' => 'p1', 'base_url' => 'https://api.anystack.sh/v1']);
    $result = $driver->activate(new LicenseRequest('AS-KEY', client: 'Ada'));

    expect($result->valid)->toBeTrue()
        ->and($driver)->toBeInstanceOf(SupportsDomainBinding::class)
        ->and($driver->boundDomains('AS-KEY'))->not->toBeEmpty();
});

it('registers both drivers in the manager', function (): void {
    expect(app(DriverManager::class)->driver('whop'))->toBeInstanceOf(WhopDriver::class)
        ->and(app(DriverManager::class)->driver('anystack'))->toBeInstanceOf(AnystackDriver::class);
});
