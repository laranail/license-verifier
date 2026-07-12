<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts;

/**
 * Persists the local record of an activated license (token + metadata),
 * keyed by license key. Implementations: file, database, cache, callback.
 */
interface LicenseStore
{
    /**
     * Persist (create or update) the record for a license key.
     *
     * @param  array<string, mixed>  $data
     */
    public function put(string $key, array $data): void;

    /**
     * Retrieve the stored record for a license key, or null.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    public function has(string $key): bool;

    public function forget(string $key): void;
}
