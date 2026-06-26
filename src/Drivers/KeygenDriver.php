<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Keygen.sh license validation — POST /v1/accounts/{account}/licenses/actions/validate-key.
 *
 * @see https://keygen.sh/docs/api/licenses/
 */
final class KeygenDriver extends AbstractHttpDriver
{
    public function name(): string
    {
        return 'keygen';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return $this->validateKey($request->key, persist: true);
    }

    public function verify(?string $key = null): VerificationResult
    {
        return $this->validateKey($key ?? (string) config('license-verifier.license_key'), persist: false);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');
        $this->store()->forget($key);

        return true;
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        return $this->http()->get($this->base().'/ping')->status() !== 0;
    }

    private function validateKey(string $key, bool $persist): VerificationResult
    {
        $response = $this->http([
            'Content-Type' => 'application/vnd.api+json',
        ])->post($this->base().'/licenses/actions/validate-key', [
            'meta' => ['key' => $key],
        ]);

        $data = (array) $response->json();
        $valid = (bool) data_get($data, 'meta.valid', false);

        if (! $valid) {
            return VerificationResult::invalid(
                LicenseStatus::Invalid,
                message: data_get($data, 'meta.detail'),
                raw: $data,
            );
        }

        $expiry = data_get($data, 'data.attributes.expiry');

        if ($persist) {
            $this->remember($key, [
                'status' => 'active',
                'expires_at' => $expiry,
                'metadata' => ['entitlements' => (array) data_get($data, 'data.attributes.metadata', [])],
            ]);
        }

        return VerificationResult::valid(expiresAt: $expiry, raw: $data);
    }

    private function base(): string
    {
        $account = (string) $this->cfg('account');

        return "/v1/accounts/{$account}";
    }
}
