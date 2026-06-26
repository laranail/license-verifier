<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Verifier\Http\Controllers\HealthController;

it('reports the doctor checks as JSON with a status', function (): void {
    $data = app(HealthController::class)->show()->getData(true);

    expect($data)->toHaveKeys(['status', 'checks'])
        ->and($data['checks'])->toBeArray()
        ->and($data['status'])->toBeIn(['healthy', 'degraded']);
});

it('returns 503 degraded when a check fails', function (): void {
    // An unknown driver makes DriverResolvesCheck fail deterministically.
    config()->set('license-verifier.default', 'does-not-exist');

    $response = app(HealthController::class)->show();

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true)['status'])->toBe('degraded');
});
