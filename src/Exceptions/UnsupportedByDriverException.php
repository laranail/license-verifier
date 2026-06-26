<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Exceptions;

final class UnsupportedByDriverException extends LicensingException
{
    public static function capability(string $driver, string $capability): self
    {
        return new self(sprintf(
            'The [%s] license driver does not support the "%s" capability.',
            $driver,
            $capability,
        ));
    }
}
