<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsDomainBinding;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Easy Digital Downloads — Software Licensing. Site-URL bound.
 *
 * @see https://easydigitaldownloads.com/docs/software-licensing-api/
 */
final class EasyDigitalDownloadsDriver extends AbstractHttpDriver implements SupportsDomainBinding
{
    public function name(): string
    {
        return 'edd';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return $this->action('activate_license', $request->key, persist: true);
    }

    public function verify(?string $key = null): VerificationResult
    {
        return $this->action('check_license', $key ?? (string) config('license-verifier.license_key'), persist: false);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');
        $response = $this->http()->get('/', array_filter([
            'edd_action' => 'deactivate_license',
            'item_id' => $this->cfg('item_id'),
            'item_name' => $this->cfg('item_name'),
            'license' => $key,
            'url' => rtrim((string) url('/'), '/'),
        ]));

        $this->store()->forget($key);

        return ($response->json('license') ?? null) === 'deactivated';
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        return $this->http()->get('/')->status() !== 0;
    }

    public function boundDomains(?string $key = null): array
    {
        return $this->storedBoundHost($key);
    }

    private function action(string $action, string $key, bool $persist): VerificationResult
    {
        $response = $this->http()->get('/', array_filter([
            'edd_action' => $action,
            'item_id' => $this->cfg('item_id'),
            'item_name' => $this->cfg('item_name'),
            'license' => $key,
            'url' => rtrim((string) url('/'), '/'),
        ]));

        $data = (array) $response->json();
        $valid = ($data['success'] ?? false) && (($data['license'] ?? null) === 'valid');

        if (! $valid) {
            return VerificationResult::invalid(
                LicenseStatus::fromRaw($data['license'] ?? 'invalid'),
                message: $data['error'] ?? null,
                raw: $data,
            );
        }

        if ($persist) {
            $this->remember($key, [
                'status' => 'active',
                'expires_at' => $data['expires'] ?? null,
                'domain' => rtrim((string) url('/'), '/'),
                'licensed_to' => $data['customer_name'] ?? null,
            ]);
        }

        return VerificationResult::valid(
            licensedTo: $data['customer_name'] ?? null,
            expiresAt: $data['expires'] ?? null,
            raw: $data,
        );
    }
}
