<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Stores;

use Closure;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;

/**
 * Delegates persistence to user-provided closures — lets an application store
 * license records wherever it likes (e.g. its own settings table or an API).
 */
final readonly class CallbackStore implements LicenseStore
{
    /**
     * @param  Closure(string, array<string, mixed>): void  $putUsing
     * @param  Closure(string): (array<string, mixed>|null)  $getUsing
     * @param  Closure(string): void  $forgetUsing
     */
    public function __construct(
        private Closure $putUsing,
        private Closure $getUsing,
        private Closure $forgetUsing,
    ) {}

    public function put(string $key, array $data): void
    {
        ($this->putUsing)($key, $data);
    }

    public function get(string $key): ?array
    {
        return ($this->getUsing)($key);
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function forget(string $key): void
    {
        ($this->forgetUsing)($key);
    }
}
