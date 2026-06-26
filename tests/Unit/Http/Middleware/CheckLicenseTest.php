<?php

declare(strict_types=1);

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Simtabi\Laranail\Licence\Verifier\Exceptions\LicensingException;
use Simtabi\Laranail\Licence\Verifier\Http\Middleware\CheckLicense;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;
use Symfony\Component\HttpKernel\Exception\HttpException;

function middlewareWith(LicenseManager $manager): CheckLicense
{
    return new CheckLicense($manager);
}

function passThrough(): Closure
{
    return fn (): ResponseFactory|Response => response('ok');
}

it('allows the request when the active driver verifies', function (): void {
    $manager = Mockery::mock(LicenseManager::class);
    $manager->shouldReceive('verify')->andReturn(VerificationResult::valid());
    $manager->shouldReceive('heartbeat')->andReturnTrue();

    $response = middlewareWith($manager)->handle(Request::create('/dashboard'), passThrough());

    expect($response->getContent())->toBe('ok');
});

it('allows the request during the grace window', function (): void {
    $manager = Mockery::mock(LicenseManager::class);
    $manager->shouldReceive('verify')->andReturn(VerificationResult::valid(status: LicenseStatus::Grace));
    $manager->shouldReceive('heartbeat')->andReturnTrue();

    $response = middlewareWith($manager)->handle(Request::create('/dashboard'), passThrough());

    expect($response->getContent())->toBe('ok');
});

it('blocks the request when the license is invalid', function (): void {
    $manager = Mockery::mock(LicenseManager::class);
    $manager->shouldReceive('verify')->andReturn(VerificationResult::invalid());

    middlewareWith($manager)->handle(Request::create('/dashboard'), passThrough());
})->throws(HttpException::class);

it('returns a JSON 403 for API requests', function (): void {
    $manager = Mockery::mock(LicenseManager::class);
    $manager->shouldReceive('verify')->andReturn(VerificationResult::invalid());

    $request = Request::create('/api/data');
    $request->headers->set('Accept', 'application/json');

    $response = middlewareWith($manager)->handle($request, passThrough());

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true)['code'])->toBe('LICENSE_INVALID');
});

it('returns a JSON 403 with the message on a license exception', function (): void {
    $manager = Mockery::mock(LicenseManager::class);
    $manager->shouldReceive('verify')->andThrow(LicensingException::serverUnreachable());

    $request = Request::create('/api/data');
    $request->headers->set('Accept', 'application/json');

    $response = middlewareWith($manager)->handle($request, passThrough());

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true)['code'])->toBe('LICENSE_ERROR');
});

it('skips excluded routes without verifying', function (): void {
    config()->set('license-verifier.excluded_routes', ['login', 'license/*']);

    $manager = Mockery::mock(LicenseManager::class);
    $manager->shouldNotReceive('verify');

    $response = middlewareWith($manager)->handle(Request::create('/login'), passThrough());

    expect($response->getContent())->toBe('ok');
});

it('flags an expiring-soon license in request attributes', function (): void {
    $manager = Mockery::mock(LicenseManager::class);
    $manager->shouldReceive('verify')->andReturn(
        VerificationResult::valid(expiresAt: now()->addDays(3)->toIso8601String())
    );
    $manager->shouldReceive('heartbeat')->andReturnTrue();

    $request = Request::create('/dashboard');
    middlewareWith($manager)->handle($request, passThrough());

    expect($request->attributes->get('license_expiring_soon'))->toBeTrue();
});
