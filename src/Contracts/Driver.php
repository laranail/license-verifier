<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts;

use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * A license source. Optional behaviours (offline tokens, refresh, heartbeat,
 * entitlements, seats, domain binding) are declared via the capability
 * interfaces in Contracts\Capabilities. The orchestrator checks for those
 * interfaces before calling them and otherwise raises UnsupportedByDriverException.
 */
interface Driver
{
    /**
     * The driver's config key (e.g. "paseto", "envato", "keygen").
     */
    public function name(): string;

    /**
     * Activate the license against the source and persist the result locally.
     */
    public function activate(LicenseRequest $request): VerificationResult;

    /**
     * Verify the (already activated) license — offline where the driver supports it,
     * otherwise online.
     */
    public function verify(?string $key = null): VerificationResult;

    /**
     * Release the activation for this installation.
     */
    public function deactivate(?string $key = null, ?string $reason = null): bool;

    /**
     * Normalized, presentable details for the active license.
     */
    public function getLicenseInfo(?string $key = null): LicenseInfo;

    /**
     * Whether the source endpoint is reachable/healthy.
     */
    public function health(): bool;

    /**
     * Field schema the UI presets render for activation, e.g.
     * [['name' => 'license_key', 'label' => '…', 'type' => 'text', 'required' => true], …].
     *
     * @return list<array<string, mixed>>
     */
    public function activationFields(): array;

    /**
     * Capability keys this driver supports (offline_tokens, refresh, heartbeat,
     * entitlements, seats, domain).
     *
     * @return list<string>
     */
    public function capabilities(): array;
}
