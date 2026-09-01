<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Lemon Squeezy license API — /v1/licenses/{activate,validate,deactivate}.
 *
 * @see https://docs.lemonsqueezy.com/api/license-api
 */
final class LemonSqueezyDriver extends AbstractHttpDriver
{
    public function name(): string
    {
        return 'lemonsqueezy';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        $instanceName = $request->client ?? $request->fingerprint ?? gethostname() ?: 'instance';

        $response = $this->http()->asForm()->post('/v1/licenses/activate', [
            'license_key' => $request->key,
            'instance_name' => $instanceName,
        ]);

        $data = (array) $response->json();

        if (! ($data['activated'] ?? false)) {
            return VerificationResult::invalid(
                LicenseStatus::Invalid,
                message: $data['error'] ?? 'Activation failed.',
                raw: $data,
            );
        }

        $this->remember($request->key, [
            'status' => $data['license_key']['status'] ?? 'active',
            'expires_at' => $data['license_key']['expires_at'] ?? null,
            'metadata' => ['instance_id' => $data['instance']['id'] ?? null],
        ]);

        return $this->resultFromPayload($data);
    }

    public function verify(?string $key = null): VerificationResult
    {
        $key ??= (string) config('license-verifier.license_key');
        $instanceId = $this->store()->get($key)['metadata']['instance_id'] ?? null;

        $response = $this->http()->asForm()->post('/v1/licenses/validate', array_filter([
            'license_key' => $key,
            'instance_id' => $instanceId,
        ]));

        $data = (array) $response->json();

        if (! ($data['valid'] ?? false)) {
            return VerificationResult::invalid(LicenseStatus::Invalid, message: $data['error'] ?? null, raw: $data);
        }

        return $this->resultFromPayload($data);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');
        $instanceId = $this->store()->get($key)['metadata']['instance_id'] ?? null;

        $response = $this->http()->asForm()->post('/v1/licenses/deactivate', array_filter([
            'license_key' => $key,
            'instance_id' => $instanceId,
        ]));

        $this->store()->forget($key);

        return (bool) $response->json('deactivated', false);
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        return $this->http()->get('/v1/licenses/validate')->status() !== 0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resultFromPayload(array $data): VerificationResult
    {
        $license = (array) ($data['license_key'] ?? []);
        $status = LicenseStatus::fromRaw($license['status'] ?? 'active');

        return VerificationResult::valid(
            status: $status,
            expiresAt: $license['expires_at'] ?? null,
            raw: $data,
        );
    }
}
