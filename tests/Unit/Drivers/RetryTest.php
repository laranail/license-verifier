<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Drivers\GumroadDriver;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;

it('retries a transient 5xx and then succeeds', function (): void {
    config()->set('license-verifier.storage.driver', 'database');
    config()->set('license-verifier.security.retry_delay', 1);

    Http::fake(['api.gumroad.com/*' => Http::sequence()
        ->push(['error' => 'temporary'], 503)
        ->push(['success' => true, 'purchase' => ['email' => 'b@e.com']], 200),
    ]);

    $result = new GumroadDriver(['product_id' => 'p', 'base_url' => 'https://api.gumroad.com'])
        ->activate(new LicenseRequest('K'));

    expect($result->valid)->toBeTrue();
    Http::assertSentCount(2); // first 503, retried → 200
});
