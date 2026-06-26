<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Stores;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use PDOException;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Throwable;

/**
 * Tiered, resilient {@see LicenseStore}: a chosen primary backend (database or
 * cache) with an encrypted local file fallback.
 *
 *  - Writes go through to the primary and mirror to the fallback (so the local
 *    copy is never staler than the last successful write).
 *  - Reads prefer the primary and fail over to the fallback ONLY when the primary
 *    is genuinely unreachable (a connection failure) — never to mask an
 *    authoritative empty: a reachable primary returning null clears the mirror so
 *    a deactivation can't be defeated by a stale file.
 *  - Writes AND deletes made during an outage are queued and replayed to the
 *    primary on recovery (so a deactivation issued while the primary is down is
 *    not silently undone by re-mirroring).
 *  - A short circuit breaker avoids paying the connection timeout on every request
 *    while the primary is down. Breaker + pending state live in the local fallback
 *    store, so they stay available even when the primary (incl. a cache primary)
 *    is the thing that is down.
 *  - Genuine (non-connection) errors are re-thrown, never silently degraded.
 *
 * The fallback MUST be a schemaless local store (a {@see FileStore}); the provider
 * wires it that way.
 */
final readonly class FallbackLicenseStore implements LicenseStore
{
    /** Reserved fallback-only bookkeeping keys (namespaced; never proxied to the primary). */
    private const string PENDING_PUT = '__lvfb_pending_put__';

    private const string PENDING_DELETE = '__lvfb_pending_delete__';

    private const string BREAKER = '__lvfb_breaker__';

    private const string SYNCED_FLAG = '__synced__';

    private const string CONNECTION_PATTERNS = '/connection refused|could not (?:find the requested |)connect|gone away|no such host|host not found|timed out|connection timed out|connection lost|can\'?t connect|unable to connect|read error on connection|connection reset|broken pipe|name or service not known/i';

    public function __construct(
        private LicenseStore $primary,
        private LicenseStore $fallback,
    ) {}

    public function put(string $key, array $data): void
    {
        if ($this->primaryIsDown()) {
            $this->queuePut($key, $data);

            return;
        }

        try {
            $this->primary->put($key, $data);
            $this->clearBreaker();
            $this->fallback->put($key, $this->withSynced($data));
            $this->reconcile();
        } catch (Throwable $e) {
            if (! $this->isConnectionError($e)) {
                throw $e;
            }

            $this->tripBreaker($e);
            $this->queuePut($key, $data);
        }
    }

    public function get(string $key): ?array
    {
        if ($this->primaryIsDown()) {
            return $this->clean($this->fallback->get($key));
        }

        try {
            // Replay queued writes/deletes first, so a not-yet-synced record is
            // never mistaken for an authoritative empty and cleared below.
            $this->reconcile();

            $value = $this->primary->get($key);
            $this->clearBreaker();

            if ($value === null) {
                // Authoritative empty — propagate the deletion to the mirror.
                $this->fallback->forget($key);
            } else {
                $this->fallback->put($key, $this->withSynced($value));
            }

            return $value;
        } catch (Throwable $e) {
            if (! $this->isConnectionError($e)) {
                throw $e;
            }

            $this->tripBreaker($e);

            return $this->clean($this->fallback->get($key));
        }
    }

    public function has(string $key): bool
    {
        // Non-mutating: no mirror refresh / reconcile side effects.
        if ($this->primaryIsDown()) {
            return $this->fallback->has($key);
        }

        try {
            $has = $this->primary->has($key);
            $this->clearBreaker();

            return $has;
        } catch (Throwable $e) {
            if (! $this->isConnectionError($e)) {
                throw $e;
            }

            $this->tripBreaker($e);

            return $this->fallback->has($key);
        }
    }

    public function forget(string $key): void
    {
        $this->fallback->forget($key);
        $this->dequeue(self::PENDING_PUT, $key);

        if ($this->primaryIsDown()) {
            $this->queueDelete($key);

            return;
        }

        try {
            $this->primary->forget($key);
            $this->clearBreaker();
        } catch (Throwable $e) {
            if (! $this->isConnectionError($e)) {
                throw $e;
            }

            $this->tripBreaker($e);
            $this->queueDelete($key);
        }
    }

    /**
     * Whether the active store is currently serving from the fallback tier.
     */
    public function onFallback(): bool
    {
        return $this->primaryIsDown();
    }

    public function pendingSyncCount(): int
    {
        return count($this->queued(self::PENDING_PUT)) + count($this->queued(self::PENDING_DELETE));
    }

    // ------------------------------------------------------------- Internals --

    /**
     * Replay queued deletes and writes back to the primary, in that order (a
     * re-put already cancelled any matching delete via dequeue).
     */
    private function reconcile(): void
    {
        $deletes = $this->queued(self::PENDING_DELETE);
        $puts = $this->queued(self::PENDING_PUT);

        if ($deletes === [] && $puts === []) {
            return;
        }

        foreach ($deletes as $key) {
            try {
                $this->primary->forget($key);
                Log::info("license storage: reconciled pending delete '{$key}' to the primary.");
            } catch (Throwable $e) {
                if (! $this->isConnectionError($e)) {
                    throw $e;
                }

                $this->tripBreaker($e);

                return; // primary went down again; keep everything queued
            }
        }
        $this->setQueue(self::PENDING_DELETE, []);

        $remaining = [];

        foreach ($puts as $key) {
            $record = $this->fallback->get($key);

            if ($record === null) {
                continue;
            }

            try {
                $this->primary->put($key, $this->clean($record) ?? []);
                $this->fallback->put($key, $this->withSynced($record));
                Log::info("license storage: reconciled pending write '{$key}' to the primary.");
            } catch (Throwable $e) {
                if (! $this->isConnectionError($e)) {
                    throw $e;
                }

                $remaining[] = $key;
                $this->tripBreaker($e);
                break;
            }
        }

        $this->setQueue(self::PENDING_PUT, $remaining);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function queuePut(string $key, array $data): void
    {
        $this->fallback->put($key, $this->withSynced($data, false));
        $this->enqueue(self::PENDING_PUT, $key);
        $this->dequeue(self::PENDING_DELETE, $key); // a fresh write cancels a queued delete

        Log::warning("license storage: primary unreachable; wrote '{$key}' to the local fallback (pending sync).");
    }

    private function queueDelete(string $key): void
    {
        $this->enqueue(self::PENDING_DELETE, $key);

        Log::warning("license storage: primary unreachable; queued delete of '{$key}' for sync on recovery.");
    }

    /**
     * Tag a record's sync state for the mirror; stripped before callers see it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withSynced(array $data, bool $synced = true): array
    {
        return array_merge($data, [self::SYNCED_FLAG => $synced]);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    private function clean(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        unset($data[self::SYNCED_FLAG]);

        return $data;
    }

    // --- pending queues + breaker (stored in the local fallback) ---

    /**
     * @return array<int, string>
     */
    private function queued(string $indexKey): array
    {
        $index = $this->fallback->get($indexKey);

        return array_values(array_filter((array) ($index['keys'] ?? []), is_string(...)));
    }

    /**
     * @param  array<int, string>  $keys
     */
    private function setQueue(string $indexKey, array $keys): void
    {
        if ($keys === []) {
            $this->fallback->forget($indexKey);

            return;
        }

        $this->fallback->put($indexKey, ['keys' => array_values($keys)]);
    }

    private function enqueue(string $indexKey, string $key): void
    {
        $this->setQueue($indexKey, array_unique([...$this->queued($indexKey), $key]));
    }

    private function dequeue(string $indexKey, string $key): void
    {
        $this->setQueue($indexKey, array_filter($this->queued($indexKey), static fn (string $k): bool => $k !== $key));
    }

    private function primaryIsDown(): bool
    {
        $breaker = $this->fallback->get(self::BREAKER);
        $until = $breaker['down_until'] ?? 0;

        return is_int($until) && time() < $until;
    }

    private function tripBreaker(Throwable $e): void
    {
        $cooldown = max(1, (int) config('license-verifier.storage.fallback_cooldown', 15));
        $this->fallback->put(self::BREAKER, ['down_until' => time() + $cooldown]);

        Log::warning('license storage: primary unreachable ('.$e->getMessage()."); serving fallback for {$cooldown}s.");
    }

    private function clearBreaker(): void
    {
        if ($this->fallback->has(self::BREAKER)) {
            $this->fallback->forget(self::BREAKER);
        }
    }

    /**
     * Recognise a genuine *connection* failure (DB or cache), as opposed to a
     * real SQL/schema/logic error which must propagate.
     */
    private function isConnectionError(Throwable $e): bool
    {
        for ($t = $e; $t instanceof Throwable; $t = $t->getPrevious()) {
            // SQLSTATE class 08 = connection exception (ANSI/Postgres).
            if (($t instanceof QueryException || $t instanceof PDOException) && str_starts_with((string) $t->getCode(), '08')) {
                return true;
            }

            if (preg_match(self::CONNECTION_PATTERNS, $t->getMessage()) === 1) {
                return true;
            }

            $class = $t::class;

            if ((str_contains($class, 'Predis') && str_contains($class, 'Connection'))
                || $class === 'RedisException') {
                return true;
            }
        }

        return false;
    }
}
