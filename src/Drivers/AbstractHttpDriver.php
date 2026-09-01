<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsDomainBinding;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsEntitlements;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsHeartbeat;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsOfflineTokens;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsRefresh;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsSeatManagement;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsSeats;
use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Drivers\Concerns\DispatchesLicenseEvents;
use Simtabi\Laranail\Licence\Verifier\Services\FingerprintGenerator;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\Capability;
use Throwable;

/**
 * Shared HTTP plumbing for online (marketplace/commerce) drivers. TLS
 * verification is ON by default (config license-verifier.security.verify_tls).
 */
abstract class AbstractHttpDriver implements Driver
{
    use DispatchesLicenseEvents;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected readonly array $config = []) {}

    public function activationFields(): array
    {
        return [
            [
                'name' => 'license_key',
                'label' => 'License key',
                'type' => 'text',
                'required' => true,
            ],
        ];
    }

    /**
     * Derived from the capability interfaces the concrete driver actually
     * implements — so `capabilities()` can never advertise something the driver
     * doesn't back (the orchestrator gates by the same interfaces).
     */
    public function capabilities(): array
    {
        return array_values(array_filter([
            $this instanceof SupportsOfflineTokens ? Capability::OfflineTokens->value : null,
            $this instanceof SupportsRefresh ? Capability::Refresh->value : null,
            $this instanceof SupportsHeartbeat ? Capability::Heartbeat->value : null,
            $this instanceof SupportsEntitlements ? Capability::Entitlements->value : null,
            $this instanceof SupportsSeats ? Capability::Seats->value : null,
            $this instanceof SupportsSeatManagement ? Capability::SeatManagement->value : null,
            $this instanceof SupportsDomainBinding ? Capability::Domain->value : null,
        ]));
    }

    /**
     * Build a configured HTTP client. Transport settings (timeout, TLS, retry)
     * may be overridden per-driver via the driver's own config block, falling
     * back to the global `license-verifier.*` defaults.
     *
     * @param  array<string, string>  $headers
     */
    protected function http(array $headers = []): PendingRequest
    {
        $request = Http::timeout((int) $this->httpConfig('timeout', config('license-verifier.timeout', 30)))
            ->acceptJson()
            ->withHeaders($headers)
            // Retry transient failures (connection errors + 5xx) with linear
            // backoff; never throw here — drivers inspect the response and normalize it.
            ->retry(
                (int) $this->httpConfig('retries', config('license-verifier.security.retries', 2)),
                (int) $this->httpConfig('retry_delay', config('license-verifier.security.retry_delay', 200)),
                static fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && (bool) $e->response->serverError()),
                throw: false,
            );

        if (! (bool) $this->httpConfig('verify_tls', config('license-verifier.security.verify_tls', true))) {
            $request = $request->withoutVerifying();
        }

        $base = $this->cfg('base_url') ?? $this->cfg('server_url') ?? $this->cfg('store_url');

        if ($base) {
            return $request->baseUrl(rtrim((string) $base, '/'));
        }

        return $request;
    }

    /**
     * A per-driver transport override (driver config block), or the global default.
     */
    protected function httpConfig(string $key, mixed $default): mixed
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * RFC 1123 / HTTP-date in GMT, for request-signing schemes.
     */
    protected function httpDate(): string
    {
        return gmdate('D, d M Y H:i:s').' GMT';
    }

    /**
     * The machine code for activation/deactivation: the caller-supplied value, or
     * the stable device fingerprint (so deactivation releases the same machine).
     */
    protected function fingerprint(?string $provided): string
    {
        return $provided !== null && $provided !== ''
            ? $provided
            : app(FingerprintGenerator::class)->generate();
    }

    protected function cfg(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    protected function store(): LicenseStore
    {
        return app(LicenseStore::class);
    }

    /**
     * The host this license was activated for (from the stored record), as a
     * single-element list for {@see SupportsDomainBinding::boundDomains()}. Reading
     * the *stored* domain (not the current host) is what makes binding enforce.
     *
     * @return list<string>
     */
    protected function storedBoundHost(?string $key): array
    {
        $key ??= (string) config('license-verifier.license_key');
        $record = (array) $this->store()->get($key);
        $stored = (string) ($record['domain'] ?? '');

        if ($stored === '') {
            return [];
        }

        $host = parse_url($stored, PHP_URL_HOST) ?: $stored;

        return [strtolower((string) $host)];
    }

    /**
     * Persist a minimal local record for an activated/verified license.
     *
     * @param  array<string, mixed>  $data
     */
    protected function remember(string $key, array $data): void
    {
        $this->store()->put($key, array_merge(['key' => $key, 'driver' => $this->name()], $data));
    }
}
