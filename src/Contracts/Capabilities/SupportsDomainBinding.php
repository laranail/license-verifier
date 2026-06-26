<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Contracts\Capabilities;

/**
 * Driver binds the license to one or more domains/sites.
 */
interface SupportsDomainBinding
{
    /**
     * @return list<string>
     */
    public function boundDomains(?string $key = null): array;
}
