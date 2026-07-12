<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts;

/**
 * Resolves the server IP reported to the license source.
 */
interface IpResolver
{
    public function resolve(): string;
}
