<?php

declare(strict_types=1);

use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

function mockManager(): LicenseManager
{
    $manager = Mockery::mock(LicenseManager::class);
    app()->instance(LicenseManager::class, $manager);

    return $manager;
}

it('can activate a license via command', function (): void {
    $manager = mockManager();
    $manager->shouldReceive('activate')->with('TEST-LICENSE-KEY')->once()
        ->andReturn(VerificationResult::valid(licensedTo: 'Acme'));
    $manager->shouldReceive('getLicenseInfo')->with('TEST-LICENSE-KEY')->once()
        ->andReturn(['status' => 'active', 'licensed_to' => 'Acme', 'expires_at' => '2027-12-31', 'seats_total' => 5]);

    $this->artisan('license:activate', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('Activating license...')
        ->expectsOutput('License activated successfully!')
        ->expectsTable(['Property', 'Value'], [
            ['Status', 'active'],
            ['Licensed to', 'Acme'],
            ['Expires', '2027-12-31'],
            ['Seats', 5],
        ])
        ->assertSuccessful();
});

it('can deactivate a license via command', function (): void {
    mockManager()->shouldReceive('deactivate')->with('TEST-LICENSE-KEY')->once()->andReturnTrue();

    $this->artisan('license:deactivate', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsQuestion('Are you sure you want to deactivate this license?', true)
        ->expectsOutput('Deactivating license...')
        ->expectsOutput('License deactivated successfully!')
        ->assertSuccessful();
});

it('can refresh a license via command', function (): void {
    mockManager()->shouldReceive('refresh')->with('TEST-LICENSE-KEY')->once()->andReturnTrue();

    $this->artisan('license:refresh', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('Refreshing license token...')
        ->expectsOutput('License token refreshed successfully!')
        ->assertSuccessful();
});

it('can validate a license via command', function (): void {
    $manager = mockManager();
    $manager->shouldReceive('isValid')->with('TEST-LICENSE-KEY')->once()->andReturnTrue();
    $manager->shouldReceive('isExpiringSoon')->with(7, 'TEST-LICENSE-KEY')->once()->andReturnFalse();

    $this->artisan('license:validate', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('Validating license...')
        ->expectsOutput('✓ License is valid')
        ->assertSuccessful();
});

it('warns when license is expiring soon', function (): void {
    $manager = mockManager();
    $manager->shouldReceive('isValid')->with('TEST-LICENSE-KEY')->once()->andReturnTrue();
    $manager->shouldReceive('isExpiringSoon')->with(7, 'TEST-LICENSE-KEY')->once()->andReturnTrue();

    $this->artisan('license:validate', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('✓ License is valid')
        ->expectsOutput('⚠ License is expiring soon!')
        ->assertSuccessful();
});

it('can display license information via command', function (): void {
    $manager = mockManager();
    $manager->shouldReceive('getLicenseInfo')->with('TEST-LICENSE-KEY')->once()
        ->andReturn(['status' => 'active', 'licensed_to' => 'Acme', 'activated_at' => '2024-01-01', 'expires_at' => '2025-12-31', 'seats_total' => 5, 'domain' => 'app.test']);
    $manager->shouldReceive('isExpiringSoon')->with(7, 'TEST-LICENSE-KEY')->once()->andReturnFalse();
    $manager->shouldReceive('requiresOnlineRefresh')->with('TEST-LICENSE-KEY')->once()->andReturnFalse();

    $this->artisan('license:info', ['key' => 'TEST-LICENSE-KEY'])
        ->expectsOutput('License Information:')
        ->expectsTable(['Property', 'Value'], [
            ['License Key', 'TEST-LIC...'],
            ['Status', 'active'],
            ['Licensed To', 'Acme'],
            ['Activated At', '2024-01-01'],
            ['Expires At', '2025-12-31'],
            ['Seats', 5],
            ['Domain', 'app.test'],
        ])
        ->assertSuccessful();
});

it('handles activation failure via command', function (): void {
    mockManager()->shouldReceive('activate')->with('INVALID-KEY')->once()
        ->andThrow(new Exception('Invalid license key'));

    $this->artisan('license:activate', ['key' => 'INVALID-KEY'])
        ->expectsOutput('Activation failed: Invalid license key')
        ->assertFailed();
});

it('prompts for license key when not provided', function (): void {
    config(['license-verifier.license_key' => null]);
    $manager = mockManager();
    $manager->shouldReceive('activate')->with('PROMPTED-KEY')->once()->andReturn(VerificationResult::valid());
    $manager->shouldReceive('getLicenseInfo')->with('PROMPTED-KEY')->once()->andReturn([]);

    $this->artisan('license:activate')
        ->expectsQuestion('Please enter your license key', 'PROMPTED-KEY')
        ->expectsOutput('License activated successfully!')
        ->assertSuccessful();
});

it('shows error when deactivation fails', function (): void {
    mockManager()->shouldReceive('deactivate')->once()->andReturnFalse();

    $this->artisan('license:deactivate', ['key' => 'TEST-KEY'])
        ->expectsQuestion('Are you sure you want to deactivate this license?', true)
        ->expectsOutput('Failed to deactivate license')
        ->assertFailed();
});

it('cancels deactivation when user declines', function (): void {
    mockManager()->shouldNotReceive('deactivate');

    $this->artisan('license:deactivate', ['key' => 'TEST-KEY'])
        ->expectsQuestion('Are you sure you want to deactivate this license?', false)
        ->expectsOutput('Deactivation cancelled')
        ->assertSuccessful();
});

it('shows error when refresh fails', function (): void {
    mockManager()->shouldReceive('refresh')->once()->andReturnFalse();

    $this->artisan('license:refresh', ['key' => 'TEST-KEY'])
        ->expectsOutput('Failed to refresh license token')
        ->assertFailed();
});

it('shows error when validation fails', function (): void {
    mockManager()->shouldReceive('isValid')->once()->andReturnFalse();

    $this->artisan('license:validate', ['key' => 'TEST-KEY'])
        ->expectsOutput('✗ License is invalid')
        ->assertFailed();
});

it('shows error when no license key for validation', function (): void {
    config(['license-verifier.license_key' => null]);

    $this->artisan('license:validate')
        ->expectsOutput('License key is required')
        ->assertFailed();
});

it('shows error when no license info available', function (): void {
    mockManager()->shouldReceive('getLicenseInfo')->once()->andReturn([]);

    $this->artisan('license:info', ['key' => 'TEST-KEY'])
        ->expectsOutput('No license information available. Please activate the license first.')
        ->assertFailed();
});

it('shows online refresh warning in license info', function (): void {
    $manager = mockManager();
    $manager->shouldReceive('getLicenseInfo')->once()->andReturn(['status' => 'active']);
    $manager->shouldReceive('isExpiringSoon')->once()->andReturnFalse();
    $manager->shouldReceive('requiresOnlineRefresh')->once()->andReturnTrue();

    $this->artisan('license:info', ['key' => 'TEST-KEY'])
        ->expectsOutput('Warning: Online refresh is required!')
        ->assertSuccessful();
});

it('uses configured license key when not provided as argument', function (): void {
    config(['license-verifier.license_key' => 'CONFIG-KEY']);
    $manager = mockManager();
    $manager->shouldReceive('isValid')->with('CONFIG-KEY')->once()->andReturnTrue();
    $manager->shouldReceive('isExpiringSoon')->with(7, 'CONFIG-KEY')->once()->andReturnFalse();

    $this->artisan('license:validate')
        ->expectsOutput('✓ License is valid')
        ->assertSuccessful();
});
