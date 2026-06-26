<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Support;

use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;

/**
 * Cached "can I reach the license server?" pre-check (ported from Botble's
 * Core::checkConnection) — drives friendlier offline messaging in the UI/CLI.
 */
final readonly class ConnectionChecker
{
    public function __construct(
        private DriverManager $drivers,
        private CacheRepository $cache,
    ) {}

    public function check(bool $fresh = false): bool
    {
        if ($fresh) {
            $this->cache->forget($this->cacheKey());
        }

        return (bool) $this->cache->remember(
            $this->cacheKey(),
            Carbon::now()->addDay(),
            fn (): bool => rescue(fn (): bool => $this->drivers->active()->health(), false) ?: false,
        );
    }

    private function cacheKey(): string
    {
        return 'license-verifier:connection:'.$this->drivers->getDefaultDriver();
    }
}
