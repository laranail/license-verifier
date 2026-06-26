<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Paddle license/entitlement activation (PaddlePay-style REST: activate/validate/deactivate).
 *
 * @see https://developer.paddle.com/api-reference/overview
 */
final class PaddleDriver extends AbstractHttpDriver
{
    public function name(): string
    {
        return 'paddle';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return $this->call('/licenses/activate', $request->key, persist: true);
    }

    public function verify(?string $key = null): VerificationResult
    {
        return $this->call('/licenses/validate', $key ?? (string) config('license-verifier.license_key'), persist: false);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');
        $response = $this->bearer()->post('/licenses/deactivate', ['license_key' => $key]);
        $this->store()->forget($key);

        return $response->successful();
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        return $this->bearer()->get('/event-types')->status() !== 0;
    }

    private function call(string $path, string $key, bool $persist): VerificationResult
    {
        $response = $this->bearer()->post($path, ['license_key' => $key]);
        $data = (array) $response->json();
        $valid = $response->successful() && (bool) data_get($data, 'valid', data_get($data, 'data.valid', false));

        if (! $valid) {
            return VerificationResult::invalid(LicenseStatus::Invalid, raw: $data);
        }

        $expiresAt = data_get($data, 'expires_at', data_get($data, 'data.expires_at'));

        if ($persist) {
            $this->remember($key, [
                'status' => 'active',
                'expires_at' => $expiresAt,
                'metadata' => ['entitlements' => (array) data_get($data, 'entitlements', [])],
            ]);
        }

        return VerificationResult::valid(expiresAt: $expiresAt, raw: $data);
    }

    private function bearer(): PendingRequest
    {
        $base = $this->cfg('sandbox') ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';

        return $this->http()->baseUrl($base)->withToken((string) $this->cfg('api_key'));
    }
}
