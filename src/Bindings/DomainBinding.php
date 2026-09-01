<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Bindings;

use Illuminate\Support\Facades\URL;

/**
 * Binds the license to one or more allowed domains/hosts.
 *
 * For marketplace drivers (Envato) the server enforces this natively. For the
 * PASETO driver, true binding requires the server to embed a `domain` claim; until
 * then this acts as a local allowlist driven by config('license-verifier.bindings.domain').
 */
final class DomainBinding
{
    public function enabled(): bool
    {
        return (bool) config('license-verifier.bindings.domain.enabled', false);
    }

    /**
     * @return list<string>
     */
    public function allowed(): array
    {
        return array_values(array_map(
            static fn ($domain): string => strtolower(trim((string) $domain)),
            (array) config('license-verifier.bindings.domain.allowed', []),
        ));
    }

    public function currentHost(): ?string
    {
        $host = parse_url(URL::to('/'), PHP_URL_HOST);

        return $host !== null && $host !== false ? strtolower((string) $host) : null;
    }

    /**
     * Whether the given (or current) host is permitted.
     */
    public function passes(?string $host = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $allowed = $this->allowed();

        if ($allowed === []) {
            return true;
        }

        $host = strtolower($host ?? $this->currentHost() ?? '');

        if ($host === '') {
            return false;
        }

        return array_any($allowed, fn ($candidate): bool => $host === $candidate || str_ends_with($host, '.'.$candidate));
    }

    public function fails(?string $host = null): bool
    {
        return ! $this->passes($host);
    }
}
