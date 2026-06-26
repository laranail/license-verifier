<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Illuminate\Http\Client\Response;
use Override;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsDomainBinding;
use Simtabi\Laranail\Licence\Verifier\Contracts\IpResolver;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Envato / CodeCanyon purchase-code activation against a Botble-style license
 * server (configurable server_url + api_key, LB-* headers, domain binding).
 * Ported from Botble's Core license client.
 */
final class EnvatoDriver extends AbstractHttpDriver implements SupportsDomainBinding
{
    public function name(): string
    {
        return 'envato';
    }

    #[Override]
    public function activationFields(): array
    {
        return [
            ['name' => 'license_key', 'label' => 'Purchase code', 'type' => 'text', 'required' => true],
            ['name' => 'client', 'label' => 'Buyer username', 'type' => 'text', 'required' => true],
            ['name' => 'agreement', 'label' => 'I agree to the license terms', 'type' => 'checkbox', 'required' => true],
        ];
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        $response = $this->lbRequest('/api/activate_license', [
            'product_id' => $this->cfg('product_id'),
            'license_code' => $request->key,
            'client_name' => $request->client,
            'verify_type' => $this->cfg('verify_type', 'envato'),
        ]);

        $data = (array) $response->json();

        if (! $response->successful() || ! ($data['status'] ?? false)) {
            return VerificationResult::invalid(
                LicenseStatus::Invalid,
                message: $data['message'] ?? 'Could not activate your license.',
                raw: $data,
            );
        }

        $this->remember($request->key, [
            'status' => 'active',
            'licensed_to' => $request->client,
            'domain' => $this->host(),
            'token' => $data['lic_response'] ?? null,
            'metadata' => ['license_file' => $data['lic_response'] ?? null],
        ]);

        return VerificationResult::valid(licensedTo: $request->client, raw: $data);
    }

    public function verify(?string $key = null): VerificationResult
    {
        $key ??= (string) config('license-verifier.license_key');
        $record = (array) $this->store()->get($key);
        $licenseFile = $record['metadata']['license_file'] ?? $record['token'] ?? null;

        if (! $licenseFile) {
            return VerificationResult::invalid(LicenseStatus::Unactivated, raw: $record);
        }

        $response = $this->lbRequest('/api/verify_license', [
            'product_id' => $this->cfg('product_id'),
            'license_file' => $licenseFile,
        ]);

        $data = (array) $response->json();

        if ($response->ok() && ($data['status'] ?? false)) {
            return VerificationResult::valid(licensedTo: $record['licensed_to'] ?? null, raw: $data);
        }

        return VerificationResult::invalid(LicenseStatus::Invalid, message: $data['message'] ?? null, raw: $data);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');
        $record = (array) $this->store()->get($key);
        $licenseFile = $record['metadata']['license_file'] ?? $record['token'] ?? null;

        $response = $this->lbRequest('/api/deactivate_license', array_filter([
            'product_id' => $this->cfg('product_id'),
            'license_file' => $licenseFile,
        ]));

        $this->store()->forget($key);

        return $response->ok() && (bool) ($response->json('status') ?? false);
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        return $this->lbRequest('/api/check_connection_ext', [], 'GET')->ok();
    }

    public function boundDomains(?string $key = null): array
    {
        return $this->storedBoundHost($key);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function lbRequest(string $path, array $data, string $method = 'POST'): Response
    {
        $request = $this->http([
            'LB-API-KEY' => (string) $this->cfg('api_key'),
            'LB-URL' => rtrim((string) url('/'), '/'),
            'LB-IP' => app(IpResolver::class)->resolve(),
            'LB-LANG' => 'english',
        ]);

        return $method === 'GET' ? $request->get($path, $data) : $request->post($path, $data);
    }

    private function host(): ?string
    {
        $host = parse_url((string) url('/'), PHP_URL_HOST);

        return $host !== false ? $host : null;
    }
}
