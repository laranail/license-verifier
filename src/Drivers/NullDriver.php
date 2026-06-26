<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\Exceptions\LicensingException;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Always-valid driver for local development and testing. Refuses to run in
 * production unless config('license-verifier.drivers.null.allow_in_production') is true.
 */
final readonly class NullDriver implements Driver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        if (app()->environment('production') && ! ($config['allow_in_production'] ?? false)) {
            throw LicensingException::invalidConfiguration(
                'The "null" license driver is disabled in production. Set LICENSE_VERIFIER_ALLOW_NULL=true to override.',
            );
        }
    }

    public function name(): string
    {
        return 'null';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return VerificationResult::valid(licensedTo: $request->client);
    }

    public function verify(?string $key = null): VerificationResult
    {
        return VerificationResult::valid();
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        return true;
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        return new LicenseInfo(status: LicenseStatus::Valid, licensedTo: 'Development');
    }

    public function health(): bool
    {
        return true;
    }

    public function activationFields(): array
    {
        return [];
    }

    public function capabilities(): array
    {
        return [];
    }
}
