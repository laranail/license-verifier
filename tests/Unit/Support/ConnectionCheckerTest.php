<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Support\ConnectionChecker;

beforeEach(function (): void {
    config()->set('cache.default', 'array');
});

it('reports the server reachable when health is ok (cached)', function (): void {
    Http::fake(['*/health' => Http::response(['data' => ['status' => 'healthy']])]);

    expect(app(ConnectionChecker::class)->check(fresh: true))->toBeTrue();
});

it('reports the server unreachable when health fails', function (): void {
    Http::fake(['*/health' => Http::response([], 500)]);

    expect(app(ConnectionChecker::class)->check(fresh: true))->toBeFalse();
});
