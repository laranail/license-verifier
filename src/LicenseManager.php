<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier;

use Carbon\Carbon;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Traits\ForwardsCalls;
use Simtabi\Laranail\Licence\Verifier\Bindings\DomainBinding;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsDomainBinding;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsEntitlements;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsHeartbeat;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsOfflineTokens;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsRefresh;
use Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities\SupportsSeatManagement;
use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseKeyResolver;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Drivers\Concerns\DispatchesLicenseEvents;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Licence\Verifier\Drivers\PasetoDriver;
use Simtabi\Laranail\Licence\Verifier\Exceptions\LicensingException;
use Simtabi\Laranail\Licence\Verifier\Exceptions\UnsupportedByDriverException;
use Simtabi\Laranail\Licence\Verifier\Services\TokenStorage;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;
use Throwable;

/**
 * The single, driver-agnostic entry point for license operations. The facade,
 * middleware, commands and the heartbeat scheduler all go through this — so the
 * configured `license-verifier.default` driver is always the one that runs.
 *
 * Lifecycle events are dispatched here (centralised), so every driver fires them.
 * Engine-only PASETO helpers are forwarded to the underlying engine via __call.
 */
class LicenseManager
{
    use DispatchesLicenseEvents;
    use ForwardsCalls;

    /**
     * A runtime driver override (set via driver()); null uses config('default').
     */
    private ?string $scopedDriver = null;

    public function __construct(
        private readonly DriverManager $drivers,
        private readonly DomainBinding $domain,
    ) {}

    /**
     * The currently active driver — the runtime-scoped one if set, else the
     * configured default.
     */
    public function activeDriver(): Driver
    {
        return $this->scopedDriver !== null
            ? $this->drivers->driver($this->scopedDriver)
            : $this->drivers->active();
    }

    /**
     * Return a manager scoped to a specific driver at runtime, without changing
     * the global default — e.g. LicenseVerifier::driver('gumroad')->verify($key).
     */
    public function driver(?string $name): static
    {
        $clone = clone $this;
        $clone->scopedDriver = $name;

        return $clone;
    }

    /**
     * Apply config overrides at runtime and re-resolve the affected services, so
     * the change takes effect immediately — e.g.
     * LicenseVerifier::configure(['default' => 'gumroad', 'drivers.gumroad.product_id' => 'x']).
     *
     * @param  array<string, mixed>  $overrides  dotted keys relative to `license-verifier.`
     */
    public function configure(array $overrides): static
    {
        foreach ($overrides as $key => $value) {
            config()->set("license-verifier.{$key}", $value);
        }

        return $this->reload();
    }

    /**
     * Flush the services that freeze config at resolution time, so subsequent
     * calls pick up runtime config changes (driver selection + config, storage
     * backend, key-resolution source). Distinct from refresh() (token refresh).
     */
    public function reload(): static
    {
        $this->drivers->forgetDrivers();
        app()->forgetInstance(LicenseStore::class);
        app()->forgetInstance(LicenseKeyResolver::class);

        return $this;
    }

    private function resolvedDriverName(): string
    {
        return $this->scopedDriver ?? $this->drivers->getDefaultDriver();
    }

    public function activate(string|LicenseRequest $request, ?string $client = null): VerificationResult
    {
        $request = $request instanceof LicenseRequest
            ? $request
            : new LicenseRequest($request, client: $client);

        if (($throttled = $this->throttle($request->key)) instanceof VerificationResult) {
            return $throttled;
        }

        $this->eventActivating($request->key, $request->client);

        try {
            $result = $this->activeDriver()->activate($request);
        } catch (Throwable $e) {
            // Normalize a transport failure into an Unreachable result rather than
            // leaking a raw ConnectionException out of activation.
            $this->eventInvalid($request->key);

            return VerificationResult::invalid(
                LicenseStatus::Unreachable,
                message: $e instanceof LicensingException ? $e->getMessage() : 'The license server is unreachable.',
            );
        }

        $this->forgetCache($request->key);

        $result->isUsable()
            ? $this->eventActivated($request->key, $result->licensedTo ?? $request->client)
            : $this->eventInvalid($request->key);

        $this->audit($request->key, $result);

        return $result;
    }

    /**
     * Append-only activation audit (opt-in via `license-verifier.audit`). Records
     * provenance — never the raw key/secret — to the configured log channel.
     */
    private function audit(string $key, VerificationResult $result): void
    {
        if (! (bool) config('license-verifier.audit.enabled', false)) {
            return;
        }

        Log::channel(config('license-verifier.audit.channel'))->info('license.activation', [
            'key_hash' => substr(hash('sha256', $key), 0, 16),
            'driver' => $this->resolvedDriverName(),
            'status' => $result->status->value,
            'valid' => $result->valid,
            'licensed_to' => $result->licensedTo,
            'at' => Carbon::now()->toIso8601String(),
        ]);
    }

    /**
     * Multi-source activation: try each driver in order and keep the first usable
     * result (for products sold through several channels). On success the winning
     * driver becomes the default so verify/deactivate target it. With no `$sources`
     * (and none configured) this is just {@see activate()} on the default driver.
     *
     * @param  list<string>|null  $sources  driver names; defaults to config('license-verifier.sources')
     */
    public function activateAcross(string|LicenseRequest $request, ?array $sources = null, ?string $client = null): VerificationResult
    {
        $sources ??= array_values(array_filter((array) config('license-verifier.sources', [])));

        if ($sources === []) {
            return $this->activate($request, $client);
        }

        $last = VerificationResult::invalid(LicenseStatus::Invalid, message: 'No license source accepted the credentials.');

        foreach ($sources as $name) {
            $result = $this->driver($name)->activate($request, $client);

            if ($result->isUsable()) {
                $this->configure(['default' => $name]);

                return $result;
            }

            $last = $result;
        }

        return $last;
    }

    /**
     * Per-key activation rate limiting (opt-in via `license-verifier.rate_limit`).
     * Returns an invalid result when the limit is exceeded, otherwise records a hit.
     */
    private function throttle(string $key): ?VerificationResult
    {
        if (! (bool) config('license-verifier.rate_limit.enabled', false)) {
            return null;
        }

        $limiter = app(RateLimiter::class);
        $bucket = 'license-verifier:activate:'.sha1($key);
        $max = (int) config('license-verifier.rate_limit.max_attempts', 5);

        if ($limiter->tooManyAttempts($bucket, $max)) {
            return VerificationResult::invalid(
                LicenseStatus::Unreachable,
                message: "Too many activation attempts. Try again in {$limiter->availableIn($bucket)} seconds.",
            );
        }

        $limiter->hit($bucket, (int) config('license-verifier.rate_limit.decay_seconds', 300));

        return null;
    }

    public function verify(?string $key = null): VerificationResult
    {
        $result = $this->resolveVerification($key);

        $result->isUsable()
            ? $this->eventVerified($key, $result->licensedTo)
            : $this->eventUnverified($key);

        $this->announceTransition($key, $result);

        return $result;
    }

    /**
     * Fire once-per-transition events (grace entered, revoked) by tracking the
     * last-announced status for the key, so a repeated verify() does not spam.
     */
    private function announceTransition(?string $key, VerificationResult $result): void
    {
        if (! (bool) config('license-verifier.events.enabled', true)) {
            return;
        }

        $cacheKey = $this->cacheKey($key).':status';
        $previous = $this->cache()->get($cacheKey);
        $current = $result->status->value;

        if ($previous === $current) {
            return;
        }

        match ($result->status) {
            LicenseStatus::Grace => $this->eventGraceStarted($key, $result->licensedTo),
            LicenseStatus::Revoked => $this->eventRevoked($key, $result->licensedTo),
            default => null,
        };

        $this->cache()->put($cacheKey, $current, now()->addDays(30));
    }

    /**
     * Resolve a verification result with resilience:
     *  - return a fresh cached result without hitting the network;
     *  - on success, cache it and remember the last-good time;
     *  - when the source is unreachable, serve the cached result as Grace within
     *    the grace window (fail-open) and fail-closed afterwards;
     *  - enforce domain binding on every path.
     */
    protected function resolveVerification(?string $key): VerificationResult
    {
        $cacheEnabled = (bool) config('license-verifier.cache.enabled', true);
        $entry = $cacheEnabled ? $this->cachedEntry($key) : null;

        if ($entry !== null && $this->isFresh($entry)) {
            return $this->withDomainBinding($entry['result']);
        }

        try {
            $result = $this->activeDriver()->verify($key);
        } catch (Throwable $e) {
            return $this->withDomainBinding($this->graceOrFail($entry, $e));
        }

        if ($result->status === LicenseStatus::Unreachable) {
            return $this->withDomainBinding($this->graceOrFail($entry, null));
        }

        if ($cacheEnabled && $result->isUsable()) {
            $this->storeEntry($key, $result);
        }

        return $this->withDomainBinding($result);
    }

    /**
     * Apply domain binding: when enabled and the current host is not allowed,
     * a usable result is downgraded to Invalid.
     */
    private function withDomainBinding(VerificationResult $result): VerificationResult
    {
        if (! $result->isUsable() || ! $this->domain->enabled()) {
            return $result;
        }

        $driver = $this->activeDriver();
        $allowed = $driver instanceof SupportsDomainBinding
            ? $this->hostAllowedByDriver($driver)
            : $this->domain->passes();

        if ($allowed) {
            return $result;
        }

        return VerificationResult::invalid(
            LicenseStatus::Invalid,
            message: 'This license is not authorized for the current domain.',
            raw: $result->raw,
        );
    }

    private function hostAllowedByDriver(SupportsDomainBinding $driver): bool
    {
        $host = $this->domain->currentHost();
        $bound = array_map(strtolower(...), $driver->boundDomains());

        return $bound === [] || ($host !== null && in_array($host, $bound, true));
    }

    /**
     * @param  array{result: VerificationResult, at: int}|null  $entry
     */
    private function graceOrFail(?array $entry, ?Throwable $e): VerificationResult
    {
        $failOpen = (bool) config('license-verifier.security.fail_open_in_grace', true);
        $graceDays = (int) config('license-verifier.grace_period_days', 7);

        if ($failOpen && $entry !== null && $this->withinGrace($entry, $graceDays)) {
            return VerificationResult::valid(
                status: LicenseStatus::Grace,
                licensedTo: $entry['result']->licensedTo,
                expiresAt: $entry['result']->expiresAt,
                raw: $entry['result']->raw,
            );
        }

        return VerificationResult::invalid(
            LicenseStatus::Unreachable,
            message: $e instanceof LicensingException ? $e->getMessage() : 'The license server is unreachable.',
        );
    }

    /**
     * @return array{result: VerificationResult, at: int}|null
     */
    private function cachedEntry(?string $key): ?array
    {
        $entry = $this->cache()->get($this->cacheKey($key));

        return is_array($entry) && ($entry['result'] ?? null) instanceof VerificationResult ? $entry : null;
    }

    private function forgetCache(?string $key): void
    {
        if ((bool) config('license-verifier.cache.enabled', true)) {
            $this->cache()->forget($this->cacheKey($key));
        }
    }

    private function storeEntry(?string $key, VerificationResult $result): void
    {
        $graceDays = (int) config('license-verifier.grace_period_days', 7);

        $this->cache()->put(
            $this->cacheKey($key),
            ['result' => $result, 'at' => now()->getTimestamp()],
            Carbon::now()->addDays(max(1, $graceDays)),
        );
    }

    /**
     * @param  array{result: VerificationResult, at: int}  $entry
     */
    private function isFresh(array $entry): bool
    {
        $ttl = (int) config('license-verifier.cache.ttl', 3600);

        return (now()->getTimestamp() - $entry['at']) < $ttl;
    }

    /**
     * @param  array{result: VerificationResult, at: int}  $entry
     */
    private function withinGrace(array $entry, int $graceDays): bool
    {
        return (now()->getTimestamp() - $entry['at']) <= $graceDays * 86400;
    }

    private function cache(): Repository
    {
        return Cache::store(config('license-verifier.cache.store'));
    }

    private function cacheKey(?string $key): string
    {
        // Normalize null/empty to the configured license key so verify(null) and a
        // keyed activate/deactivate(forgetCache) resolve to the SAME cache entry.
        $key = $key !== null && $key !== '' ? $key : (string) config('license-verifier.license_key', '');
        $prefix = (string) config('license-verifier.cache.key_prefix', 'license-verifier');

        return $prefix.':verify:'.$this->resolvedDriverName().':'.hash('sha256', $key);
    }

    public function isValid(?string $key = null): bool
    {
        return $this->verify($key)->isUsable();
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $this->eventDeactivating($key);

        $deactivated = $this->activeDriver()->deactivate($key, $reason);
        $this->forgetCache($key);

        if ($deactivated) {
            $this->eventDeactivated($key);
        }

        return $deactivated;
    }

    /**
     * Normalized license details as an array (facade-compatible).
     *
     * @return array<string, mixed>
     */
    public function getLicenseInfo(?string $key = null): array
    {
        return $this->licenseInfo($key)->toArray();
    }

    public function licenseInfo(?string $key = null): LicenseInfo
    {
        return $this->activeDriver()->getLicenseInfo($key);
    }

    public function licensedTo(?string $key = null): ?string
    {
        return $this->licenseInfo($key)->licensedTo;
    }

    /**
     * The stored license token/credential for the active driver, wherever it is
     * persisted (PASETO encrypted token storage, or the configured LicenseStore
     * for the other drivers). Used e.g. by the product-updater to authorize downloads.
     */
    public function currentToken(?string $key = null): ?string
    {
        $key = $key !== null && $key !== '' ? $key : (string) config('license-verifier.license_key');

        if ($key === '') {
            return null;
        }

        if ($this->activeDriver() instanceof PasetoDriver) {
            return rescue(fn (): ?string => app(TokenStorage::class)->retrieve($key));
        }

        return app(LicenseStore::class)->get($key)['token'] ?? null;
    }

    public function refresh(?string $key = null): bool
    {
        $driver = $this->activeDriver();

        $refreshed = $driver instanceof SupportsRefresh && $driver->refresh($key);

        if ($refreshed) {
            $this->eventRefreshed($key);
        }

        return $refreshed;
    }

    public function heartbeat(?string $key = null): bool
    {
        $driver = $this->activeDriver();

        // Drivers that do not support heartbeats simply succeed (no-op).
        if (! $driver instanceof SupportsHeartbeat) {
            return true;
        }

        $sent = $driver->heartbeat($key);

        if ($sent) {
            $this->eventHeartbeatSent($key);
        }

        return $sent;
    }

    /**
     * @return array<string, mixed>
     */
    public function entitlements(?string $key = null): array
    {
        $driver = $this->activeDriver();

        return $driver instanceof SupportsEntitlements ? $driver->entitlements($key) : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEntitlements(): array
    {
        return $this->entitlements();
    }

    public function entitledTo(string $feature, ?string $key = null): bool
    {
        $driver = $this->activeDriver();

        return $driver instanceof SupportsEntitlements && $driver->entitledTo($feature, $key);
    }

    public function isServerHealthy(): bool
    {
        // A connection failure means "not reachable", not an error to propagate.
        try {
            return $this->activeDriver()->health();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function seats(?string $key = null): array
    {
        $driver = $this->activeDriver();

        return $driver instanceof SupportsSeatManagement ? $driver->listSeats($key) : [];
    }

    public function revokeSeat(string $target, ?string $key = null): bool
    {
        $driver = $this->activeDriver();

        $revoked = $driver instanceof SupportsSeatManagement && $driver->revokeSeat($target, $key);

        if ($revoked) {
            $this->eventSeatRevoked($target, $key);
        }

        return $revoked;
    }

    public function supportsSeatManagement(): bool
    {
        return $this->activeDriver() instanceof SupportsSeatManagement;
    }

    public function isExpiringSoon(int $daysThreshold = 7, ?string $key = null): bool
    {
        $expiresAt = $this->licenseInfo($key)->expiresAt;

        if ($expiresAt === null) {
            return false;
        }

        $days = now()->diffInDays(Carbon::parse($expiresAt), false);

        return $days > 0 && $days <= $daysThreshold;
    }

    public function requiresOnlineRefresh(?string $key = null): bool
    {
        $driver = $this->activeDriver();

        return $driver instanceof SupportsOfflineTokens && $driver->requiresOnlineRefresh($key);
    }

    /**
     * Forward engine-only PASETO helpers (isInGracePeriod, isExpiringSoon,
     * requiresOnlineRefresh, startGracePeriod, clearAll, validate, …) to the
     * active driver, falling back to the PASETO engine where applicable.
     *
     * @param  array<int, mixed>  $parameters
     */
    public function __call(string $method, array $parameters): mixed
    {
        $driver = $this->activeDriver();

        if (method_exists($driver, $method)) {
            return $this->forwardCallTo($driver, $method, $parameters);
        }

        if ($driver instanceof PasetoDriver && method_exists($driver->engine(), $method)) {
            return $this->forwardCallTo($driver->engine(), $method, $parameters);
        }

        throw UnsupportedByDriverException::capability($driver->name(), $method);
    }
}
