<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Illuminate\Http\Client\Response;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Configurable escape-hatch driver. Endpoints, verbs and the response field
 * mapping are all driven by config('license-verifier.drivers.generic'), so any
 * bespoke or smaller service (Payhip, FastSpring, Appsero, WC Key Manager,
 * Polar.sh, SureCart, custom servers) works without writing a new driver.
 */
final class GenericHttpDriver extends AbstractHttpDriver
{
    public function name(): string
    {
        return 'generic';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return $this->call('activate', $request->key, $request);
    }

    public function verify(?string $key = null): VerificationResult
    {
        return $this->call('validate', $key ?? (string) config('license-verifier.license_key'));
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');
        $response = $this->dispatch('deactivate', $key);
        $this->store()->forget($key);

        return $response?->successful() ?? true;
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        $endpoint = (array) ($this->endpoints()['health'] ?? []);

        if ($endpoint === []) {
            return true;
        }

        return $this->dispatch('health', null)?->successful() ?? false;
    }

    private function call(string $action, string $key, ?LicenseRequest $request = null): VerificationResult
    {
        $response = $this->dispatch($action, $key, $request);

        if (! $response instanceof Response) {
            return VerificationResult::invalid(LicenseStatus::Invalid, message: "No endpoint configured for [{$action}].");
        }

        $data = (array) $response->json();
        $map = (array) $this->cfg('response_map', []);
        $valid = (bool) data_get($data, $map['valid'] ?? 'valid', $response->successful());

        if (! $valid) {
            return VerificationResult::invalid(LicenseStatus::Invalid, raw: $data);
        }

        $this->remember($key, [
            'status' => (string) (data_get($data, $map['status'] ?? 'status', 'active')),
            'expires_at' => data_get($data, $map['expires_at'] ?? 'expires_at'),
            'metadata' => ['entitlements' => (array) data_get($data, $map['entitlements'] ?? 'entitlements', [])],
        ]);

        return VerificationResult::valid(
            status: LicenseStatus::fromRaw((string) data_get($data, $map['status'] ?? 'status', 'active')),
            expiresAt: data_get($data, $map['expires_at'] ?? 'expires_at'),
            raw: $data,
        );
    }

    private function dispatch(string $action, ?string $key, ?LicenseRequest $request = null): ?Response
    {
        $endpoint = (array) ($this->endpoints()[$action] ?? []);

        if ($endpoint === []) {
            return null;
        }

        $method = strtoupper((string) ($endpoint['method'] ?? 'POST'));
        $path = (string) ($endpoint['path'] ?? '/');
        $payload = array_filter([
            'license_key' => $key,
            'client' => $request?->client,
            'fingerprint' => $request?->fingerprint,
        ], static fn (?string $v): bool => $v !== null);

        $http = $this->http((array) $this->cfg('headers', []));

        return $method === 'GET' ? $http->get($path, $payload) : $http->send($method, $path, ['json' => $payload]);
    }

    /**
     * @return array<string, mixed>
     */
    private function endpoints(): array
    {
        return (array) $this->cfg('endpoints', []);
    }
}
