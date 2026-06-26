<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * @method static LicenseManager driver(?string $name)
 * @method static LicenseManager configure(array $overrides)
 * @method static LicenseManager reload()
 * @method static VerificationResult activate(string|LicenseRequest $request, ?string $client = null)
 * @method static VerificationResult verify(?string $licenseKey = null)
 * @method static bool isValid(?string $licenseKey = null)
 * @method static bool deactivate(?string $licenseKey = null, ?string $reason = null)
 * @method static array getLicenseInfo(?string $licenseKey = null)
 * @method static LicenseInfo licenseInfo(?string $licenseKey = null)
 * @method static ?string licensedTo(?string $licenseKey = null)
 * @method static ?string currentToken(?string $licenseKey = null)
 * @method static bool refresh(?string $licenseKey = null)
 * @method static bool heartbeat(?string $licenseKey = null)
 * @method static array entitlements(?string $licenseKey = null)
 * @method static array getEntitlements()
 * @method static bool entitledTo(string $feature, ?string $licenseKey = null)
 * @method static bool isServerHealthy()
 * @method static bool isExpiringSoon(int $daysThreshold = 7, ?string $licenseKey = null)
 * @method static bool requiresOnlineRefresh(?string $licenseKey = null)
 * @method static bool isInGracePeriod()
 * @method static void startGracePeriod()
 * @method static void clearAll()
 *
 * @see LicenseManager
 */
final class LicenceVerifier extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LicenseManager::class;
    }
}
