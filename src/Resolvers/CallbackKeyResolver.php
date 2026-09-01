<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Resolvers;

use Closure;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseKeyResolver;

/**
 * Resolves the license key via an application-supplied closure.
 */
final readonly class CallbackKeyResolver implements LicenseKeyResolver
{
    /**
     * @param  Closure(): (string|null)  $resolveUsing
     * @param  Closure(): array<string, mixed>  $detailsUsing
     */
    public function __construct(
        private Closure $resolveUsing,
        private ?Closure $detailsUsing = null,
    ) {}

    public function resolve(): ?string
    {
        $key = ($this->resolveUsing)();

        return $key !== null && $key !== '' ? (string) $key : null;
    }

    public function details(): array
    {
        if (! $this->detailsUsing instanceof Closure) {
            return [];
        }

        return (array) ($this->detailsUsing)();
    }
}
