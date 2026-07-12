<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Services;

use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Exceptions\LicensingException;
use Throwable;

/**
 * Persists the PASETO offline token and protocol metadata through the configured
 * {@see LicenseStore}, so `storage.driver` (file|database|cache) governs where
 * PASETO state lives — all encrypted at rest, with the resilient local fallback
 * applied at the store layer. This is a thin packing layer with no storage logic
 * of its own; the public API is unchanged so the engine and its mocks are intact.
 *
 * - The per-key token lives in the record keyed by the license key.
 * - The global server metadata (public-key bundle, refresh-after, entitlements,
 *   grace data, last-heartbeat) is packed into the reserved server record's
 *   encrypted `metadata`.
 */
class TokenStorage
{
    /** Reserved key holding the global PASETO server-metadata record. */
    private const string SERVER_KEY = '__paseto_server__';

    /** Default record key when none is supplied. */
    private const string DEFAULT_KEY = 'default';

    // ---------------------------------------------------------------- Token --

    public function store(string $token, ?string $key = 'default'): void
    {
        try {
            $record = $this->key($key);

            $this->records()->put($record, [
                'key' => $record,
                'driver' => 'paseto',
                'token' => $token,
            ]);
        } catch (Throwable $e) {
            throw LicensingException::tokenStorageFailed($e->getMessage());
        }
    }

    public function retrieve(?string $key = 'default'): ?string
    {
        $record = $this->records()->get($this->key($key));

        $token = $record['token'] ?? null;

        return is_string($token) ? $token : null;
    }

    public function delete(?string $key = 'default'): void
    {
        $this->records()->forget($this->key($key));
    }

    public function exists(?string $key = 'default'): bool
    {
        return $this->records()->has($this->key($key));
    }

    // ----------------------------------------------------- Server metadata --

    public function storeLastHeartbeat(): void
    {
        $this->writeServerMetadata(['last_heartbeat' => time()]);
    }

    public function getLastHeartbeat(): ?int
    {
        $value = $this->serverMetadata()['last_heartbeat'] ?? null;

        return $value === null ? null : (int) $value;
    }

    /**
     * @param  array<string, mixed>  $bundle
     */
    public function storePublicKeyBundle(array $bundle): void
    {
        $this->writeServerMetadata(['bundle' => $bundle]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPublicKeyBundle(): ?array
    {
        $bundle = $this->serverMetadata()['bundle'] ?? null;

        return is_array($bundle) ? $bundle : null;
    }

    public function storeRefreshAfter(string $refreshAfter): void
    {
        $this->writeServerMetadata(['refresh_after' => $refreshAfter]);
    }

    public function getRefreshAfter(): ?string
    {
        $value = $this->serverMetadata()['refresh_after'] ?? null;

        return $value === null ? null : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $entitlements
     */
    public function storeEntitlements(array $entitlements): void
    {
        $this->writeServerMetadata(['entitlements' => $entitlements]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getEntitlements(): array
    {
        $entitlements = $this->serverMetadata()['entitlements'] ?? [];

        return is_array($entitlements) ? $entitlements : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeGracePeriodData(array $data): void
    {
        $this->writeServerMetadata(['grace' => $data]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getGracePeriodData(): ?array
    {
        $grace = $this->serverMetadata()['grace'] ?? null;

        return is_array($grace) ? $grace : null;
    }

    /**
     * Clear the stored license state for this app: the configured license key,
     * the default record, and the global PASETO server metadata.
     */
    public function clearAll(): void
    {
        $store = $this->records();

        $store->forget(self::SERVER_KEY);
        $store->forget(self::DEFAULT_KEY);

        $configured = (string) config('license-verifier.license_key', '');

        if ($configured !== '') {
            $store->forget($this->key($configured));
        }
    }

    // --------------------------------------------------------------- Internals --

    private function records(): LicenseStore
    {
        return app(LicenseStore::class);
    }

    private function key(?string $key): string
    {
        return $key !== null && $key !== '' ? $key : self::DEFAULT_KEY;
    }

    /**
     * @return array<string, mixed>
     */
    private function serverMetadata(): array
    {
        $record = $this->records()->get(self::SERVER_KEY);
        $metadata = $record['metadata'] ?? null;

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * Merge a patch into the global server-metadata record (read-modify-write).
     *
     * @param  array<string, mixed>  $patch
     */
    private function writeServerMetadata(array $patch): void
    {
        $this->records()->put(self::SERVER_KEY, [
            'key' => self::SERVER_KEY,
            'driver' => 'paseto',
            'metadata' => array_merge($this->serverMetadata(), $patch),
        ]);
    }
}
