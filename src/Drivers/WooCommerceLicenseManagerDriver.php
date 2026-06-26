<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * License Manager for WooCommerce REST API (lmfwc/v2).
 *
 * @see https://licensemanager.at/docs/rest-api/
 */
final class WooCommerceLicenseManagerDriver extends AbstractHttpDriver
{
    public function name(): string
    {
        return 'woocommerce';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return $this->call('activate', $request->key, persist: true);
    }

    public function verify(?string $key = null): VerificationResult
    {
        return $this->call('validate', $key ?? (string) config('license-verifier.license_key'), persist: false);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');
        $response = $this->call('deactivate', $key, persist: false);
        $this->store()->forget($key);

        return $response->valid;
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        return $this->http()->get((string) $this->cfg('store_url'))->status() !== 0;
    }

    private function call(string $action, string $key, bool $persist): VerificationResult
    {
        $response = $this->http()
            ->withBasicAuth((string) $this->cfg('consumer_key'), (string) $this->cfg('consumer_secret'))
            ->get(rtrim((string) $this->cfg('store_url'), '/')."/wp-json/lmfwc/v2/licenses/{$action}/".rawurlencode($key));

        $data = (array) $response->json();
        $valid = (bool) ($data['success'] ?? false);

        if (! $valid) {
            return VerificationResult::invalid(LicenseStatus::Invalid, raw: $data);
        }

        $license = (array) ($data['data'] ?? []);

        if ($persist) {
            $this->remember($key, [
                'status' => (string) ($license['status'] ?? 'active'),
                'expires_at' => $license['expiresAt'] ?? null,
            ]);
        }

        // lmfwc reports a numeric status (3 = ACTIVE) that doesn't map via fromRaw();
        // the `success:true` above already confirms the license is usable.
        return VerificationResult::valid(
            expiresAt: $license['expiresAt'] ?? null,
            raw: $data,
        );
    }
}
