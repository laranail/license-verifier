<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Gumroad license verification — POST /v2/licenses/verify.
 *
 * @see https://gumroad.com/help/article/76-license-keys
 */
final class GumroadDriver extends AbstractHttpDriver
{
    public function name(): string
    {
        return 'gumroad';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return $this->check($request->key, incrementUses: true);
    }

    public function verify(?string $key = null): VerificationResult
    {
        $key ??= (string) config('license-verifier.license_key');

        return $this->check($key, incrementUses: false);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');

        $response = $this->http()->asForm()->post('/v2/licenses/decrement_uses_count', [
            'access_token' => $this->cfg('access_token'),
            'product_id' => $this->cfg('product_id'),
            'license_key' => $key,
        ]);

        $this->store()->forget($key);

        return $response->successful() && (bool) $response->json('success', false);
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        return $this->http()->get('/v2/products')->status() !== 0;
    }

    private function check(string $key, bool $incrementUses): VerificationResult
    {
        $payload = array_filter([
            'product_id' => $this->cfg('product_id'),
            'product_permalink' => $this->cfg('product_permalink'),
            'license_key' => $key,
            'increment_uses_count' => $incrementUses ? 'true' : 'false',
        ], static fn ($v): bool => $v !== null);

        $response = $this->http()->asForm()->post('/v2/licenses/verify', $payload);
        $data = (array) $response->json();

        if (! $response->successful() || ! ($data['success'] ?? false)) {
            return VerificationResult::invalid(
                LicenseStatus::Invalid,
                message: $data['message'] ?? 'License key is invalid.',
                raw: $data,
            );
        }

        $purchase = (array) ($data['purchase'] ?? []);
        $refunded = (bool) ($purchase['refunded'] ?? false);
        $disputed = (bool) ($purchase['chargebacked'] ?? false);

        if ($refunded || $disputed) {
            return VerificationResult::invalid(LicenseStatus::Revoked, message: 'License refunded or disputed.', raw: $data);
        }

        $this->remember($key, [
            'status' => 'active',
            'licensed_to' => $purchase['email'] ?? null,
            'metadata' => ['uses' => $data['uses'] ?? null],
        ]);

        return VerificationResult::valid(
            licensedTo: $purchase['email'] ?? null,
            activatedAt: $purchase['created_at'] ?? null,
            raw: $data,
        );
    }
}
