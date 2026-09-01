<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * unlock.sh licensing & distribution — license/machine activation & validation.
 *
 * @see https://unlock.sh
 */
final class UnlockShDriver extends AbstractHttpDriver
{
    public function name(): string
    {
        return 'unlocksh';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        $response = $this->bearer()->post('/v1/licenses/'.rawurlencode($request->key).'/activate', array_filter([
            'fingerprint' => $request->fingerprint,
            'name' => $request->client,
        ]));

        return $this->result($response, $request->key, persist: true);
    }

    public function verify(?string $key = null): VerificationResult
    {
        $key ??= (string) config('license-verifier.license_key');
        $response = $this->bearer()->post('/v1/licenses/'.rawurlencode($key).'/validate');

        return $this->result($response, $key, persist: false);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');
        $response = $this->bearer()->post('/v1/licenses/'.rawurlencode($key).'/deactivate');
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
        return $this->bearer()->get('/v1/ping')->status() !== 0;
    }

    private function result(Response $response, string $key, bool $persist): VerificationResult
    {
        $data = (array) $response->json();
        $valid = $response->successful() && (bool) data_get($data, 'valid', data_get($data, 'data.valid', $response->successful()));

        if (! $valid) {
            return VerificationResult::invalid(LicenseStatus::Invalid, raw: $data);
        }

        $expiresAt = data_get($data, 'data.expires_at', data_get($data, 'expires_at'));

        if ($persist) {
            $this->remember($key, ['status' => 'active', 'expires_at' => $expiresAt]);
        }

        return VerificationResult::valid(expiresAt: $expiresAt, raw: $data);
    }

    private function bearer(): PendingRequest
    {
        return $this->http()->withToken((string) $this->cfg('api_key'));
    }
}
