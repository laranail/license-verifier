<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Stores;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Throwable;

/**
 * Stores license records in a Laravel cache store.
 */
final readonly class CacheStore implements LicenseStore
{
    private Repository $cache;

    private ?int $ttl;

    public function __construct(?string $store = null, ?int $ttl = null)
    {
        $this->cache = Cache::store($store ?? config('license-verifier.cache.store'));

        // Normalize any non-positive TTL (incl. an explicit 0) to "forever"; a
        // literal 0 would otherwise make the cache store expire records immediately.
        $ttl ??= (int) config('license-verifier.cache.ttl', 3600);
        $this->ttl = $ttl > 0 ? $ttl : null;
    }

    public function put(string $key, array $data): void
    {
        $this->cache->put($this->cacheKey($key), encrypt(json_encode($data)), $this->ttl);
    }

    public function get(string $key): ?array
    {
        $value = $this->cache->get($this->cacheKey($key));

        if ($value === null) {
            return null;
        }

        try {
            return json_decode((string) decrypt($value), true);
        } catch (Throwable) {
            return is_array($value) ? $value : null; // legacy plaintext entry
        }
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->cacheKey($key));
    }

    public function forget(string $key): void
    {
        $this->cache->forget($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        $prefix = (string) config('license-verifier.cache.key_prefix', 'license-verifier');

        return $prefix.':record:'.hash('sha256', $key);
    }
}
