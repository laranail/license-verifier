<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsEntitlements;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsHeartbeat;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsOfflineTokens;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsRefresh;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsSeatManagement;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsSeats;
use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\LicenceVerifier;
use Simtabi\Laranail\Licence\Verifier\Services\FingerprintGenerator;
use Simtabi\Laranail\Licence\Verifier\Services\LicensingApiClient;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\Capability;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * The default driver: a thin adapter over the PASETO/Ed25519 engine
 * (LicenceVerifier + its services) that talks to a laranail/license-kit server.
 */
final readonly class PasetoDriver implements Driver, SupportsEntitlements, SupportsHeartbeat, SupportsOfflineTokens, SupportsRefresh, SupportsSeatManagement, SupportsSeats
{
    public function __construct(private LicenceVerifier $engine) {}

    public function name(): string
    {
        return 'paseto';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        $this->engine->activate($request->key);

        return $this->resultFor($request->key, $request->client);
    }

    public function verify(?string $key = null): VerificationResult
    {
        return $this->resultFor($key);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        return $this->engine->deactivate($key, $reason);
    }

    /**
     * Build the verification result. An optional client (buyer) name supplied at
     * activation is surfaced as licensedTo when the token carries none.
     */
    private function resultFor(?string $key, ?string $client = null): VerificationResult
    {
        $info = LicenseInfo::fromArray($this->engine->getLicenseInfo($key));

        if ($this->engine->isValid($key)) {
            return VerificationResult::valid(
                status: LicenseStatus::Valid,
                licensedTo: $info->licensedTo ?? $client,
                activatedAt: $info->activatedAt,
                expiresAt: $info->expiresAt,
                raw: $info->raw,
            );
        }

        if ($this->engine->isInGracePeriod()) {
            return VerificationResult::valid(status: LicenseStatus::Grace, raw: $info->raw);
        }

        $status = $info->raw === [] ? LicenseStatus::Unactivated : LicenseStatus::Invalid;

        return VerificationResult::invalid($status, raw: $info->raw);
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        return LicenseInfo::fromArray($this->engine->getLicenseInfo($key));
    }

    public function health(): bool
    {
        return $this->engine->isServerHealthy();
    }

    public function activationFields(): array
    {
        return [
            [
                'name' => 'license_key',
                'label' => 'License key',
                'type' => 'text',
                'required' => true,
                'placeholder' => 'XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX',
            ],
        ];
    }

    public function capabilities(): array
    {
        return [
            Capability::OfflineTokens->value,
            Capability::Refresh->value,
            Capability::Heartbeat->value,
            Capability::Entitlements->value,
            Capability::Seats->value,
            Capability::SeatManagement->value,
        ];
    }

    public function isValidOffline(?string $key = null): bool
    {
        return $this->engine->isValid($key);
    }

    public function requiresOnlineRefresh(?string $key = null): bool
    {
        return $this->engine->requiresOnlineRefresh($key);
    }

    public function refresh(?string $key = null): bool
    {
        return $this->engine->refresh($key);
    }

    public function heartbeat(?string $key = null): bool
    {
        return $this->engine->heartbeat($key);
    }

    public function entitlements(?string $key = null): array
    {
        return $this->engine->getEntitlements();
    }

    public function entitledTo(string $feature, ?string $key = null): bool
    {
        $entitlements = $this->entitlements($key);

        if (! array_key_exists($feature, $entitlements)) {
            return false;
        }

        return (bool) $entitlements[$feature];
    }

    public function seatsUsed(?string $key = null): ?int
    {
        return $this->getLicenseInfo($key)->seatsUsed;
    }

    public function seatsTotal(?string $key = null): ?int
    {
        return $this->getLicenseInfo($key)->seatsTotal;
    }

    public function listSeats(?string $key = null): array
    {
        $key = $key !== null && $key !== '' ? $key : (string) config('license-verifier.license_key');

        if ($key === '') {
            return [];
        }

        return app(LicensingApiClient::class)->usages($key, app(FingerprintGenerator::class)->generate());
    }

    public function revokeSeat(string $target, ?string $key = null): bool
    {
        $key = $key !== null && $key !== '' ? $key : (string) config('license-verifier.license_key');

        if ($key === '') {
            return false;
        }

        return app(LicensingApiClient::class)->revokeUsage($key, app(FingerprintGenerator::class)->generate(), $target);
    }

    /**
     * Escape hatch for engine-only helpers (grace, reminder, expiring-soon, …).
     */
    public function engine(): LicenceVerifier
    {
        return $this->engine;
    }
}
