<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * LicenseSpring Customer API v4 (hardware-id locking, feature checks). When a
 * `shared_key` is configured each request is signed with the published HMAC-SHA256
 * "Date" signature (Authorization: algorithm="hmac-sha256", headers="date", …).
 *
 * @see https://docs.licensespring.com/license-api/authentication
 */
final class LicenseSpringDriver extends AbstractHttpDriver
{
    public function name(): string
    {
        return 'licensespring';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return $this->check($request->key, $this->fingerprint($request->fingerprint), persist: true);
    }

    public function verify(?string $key = null): VerificationResult
    {
        return $this->check($key ?? (string) config('license-verifier.license_key'), $this->fingerprint(null), persist: false);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');

        rescue(fn () => $this->http($this->signedHeaders())->post('/api/v4/deactivate_license', array_filter([
            'product' => $this->cfg('product'),
            'license_key' => $key,
            'hardware_id' => $this->fingerprint(null),
        ])), report: false);

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
        return $this->http($this->signedHeaders())->get('/api/v4/check_license')->status() !== 0;
    }

    private function check(string $key, string $hardwareId, bool $persist): VerificationResult
    {
        $response = $this->http($this->signedHeaders())
            ->get('/api/v4/check_license', array_filter([
                'product' => $this->cfg('product'),
                'license_key' => $key,
                'hardware_id' => $hardwareId,
            ]));

        $data = (array) $response->json();
        $valid = ($data['license_active'] ?? false) && ($data['license_enabled'] ?? false);

        if (! $valid) {
            return VerificationResult::invalid(LicenseStatus::Invalid, raw: $data);
        }

        if ($persist) {
            $this->remember($key, [
                'status' => 'active',
                'expires_at' => $data['validity_period'] ?? null,
                'metadata' => ['features' => $data['product_features'] ?? []],
            ]);
        }

        return VerificationResult::valid(expiresAt: $data['validity_period'] ?? null, raw: $data);
    }

    /**
     * Api-Key header plus the HMAC "Date" signature when a shared key is set.
     *
     * @return array<string, string>
     */
    private function signedHeaders(): array
    {
        $headers = ['Api-Key' => (string) $this->cfg('api_key')];

        $sharedKey = (string) $this->cfg('shared_key');

        if ($sharedKey !== '') {
            $date = $this->httpDate();
            $signature = base64_encode(hash_hmac('sha256', "date: {$date}", $sharedKey, true));

            $headers['Date'] = $date;
            $headers['Authorization'] = 'algorithm="hmac-sha256", headers="date", signature="'.$signature.'"';
        }

        return $headers;
    }
}
