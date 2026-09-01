<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsDomainBinding;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Anystack.sh licensing (REST API v1) with fingerprint/host activation.
 *
 * Activation returns an activation id + license id, stored together as
 * "{activationId}|{licenseId}" so deactivation can target the activation without
 * extra storage. The activation host is bound, so this driver supports domain binding.
 *
 * @see https://anystack.sh/docs/api
 */
final class AnystackDriver extends AbstractHttpDriver implements SupportsDomainBinding
{
    public function name(): string
    {
        return 'anystack';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        $host = $this->host();

        $response = $this->http($this->auth())->post("/products/{$this->cfg('product_id')}/licenses/activate-key", [
            'key' => $request->key,
            'fingerprint' => $host,
            'hostname' => $host,
            'platform' => 'web',
        ]);

        $data = (array) $response->json('data', []);
        $activationId = (string) ($data['id'] ?? '');

        if (! $response->successful() || $activationId === '') {
            return VerificationResult::invalid(
                LicenseStatus::Invalid,
                message: (string) ($response->json('message') ?? 'License key could not be activated.'),
                raw: (array) $response->json(),
            );
        }

        $this->remember($request->key, [
            'licensed_to' => $request->client,
            'domain' => $host,
            'token' => $activationId.'|'.($data['license_id'] ?? ''),
            'status' => 'active',
        ]);

        return VerificationResult::valid(licensedTo: $request->client, raw: $data);
    }

    public function verify(?string $key = null): VerificationResult
    {
        $key ??= (string) config('license-verifier.license_key');
        $record = (array) $this->store()->get($key);

        $response = $this->http($this->auth())->post("/products/{$this->cfg('product_id')}/licenses/validate-key", [
            'key' => $key,
            'fingerprint' => (string) ($record['domain'] ?? $this->host()),
        ]);

        $valid = $response->successful() && ($response->json('meta.valid', true) !== false);

        return $valid
            ? VerificationResult::valid(licensedTo: $record['licensed_to'] ?? null, raw: (array) $response->json())
            : VerificationResult::invalid(LicenseStatus::Invalid, message: 'License is no longer valid.', raw: (array) $response->json());
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');
        $record = (array) $this->store()->get($key);
        $token = (string) ($record['token'] ?? '');
        $activationId = explode('|', $token)[0];

        $ok = true;

        if ($activationId !== '') {
            $ok = $this->http($this->auth())
                ->delete("/products/{$this->cfg('product_id')}/licenses/activations/{$activationId}")
                ->successful();
        }

        $this->store()->forget($key);

        return $ok;
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        return $this->http($this->auth())->get('/ping')->status() !== 0;
    }

    /**
     * @return list<string>
     */
    public function boundDomains(?string $key = null): array
    {
        return $this->storedBoundHost($key);
    }

    private function host(): string
    {
        return rtrim((string) url('/'), '/');
    }

    /**
     * @return array<string, string>
     */
    private function auth(): array
    {
        $apiKey = (string) $this->cfg('api_key', '');

        return $apiKey !== '' ? ['Authorization' => "Bearer {$apiKey}"] : [];
    }
}
