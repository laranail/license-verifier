<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\Services\FingerprintGenerator;

/**
 * Prints this device's fingerprint and metadata (for machine registration / support).
 */
final class FingerprintCommand extends Command
{
    protected $signature = 'laranail::license-verifier.fingerprint {--json}';

    protected $description = 'Show this device fingerprint and environment metadata';

    /** @var list<string> */
    protected array $commandAliases = ['license:fingerprint'];

    public function handle(FingerprintGenerator $fingerprints): int
    {
        $fingerprint = $fingerprints->generate();
        $metadata = $fingerprints->getMetadata();

        if ($this->wantsJson()) {
            $this->renderJson(['fingerprint' => $fingerprint, 'metadata' => $metadata]);

            return self::SUCCESS;
        }

        $this->services->display()->keyValue(
            array_merge(['Fingerprint' => $fingerprint], $metadata),
            'Device fingerprint',
        );

        return self::SUCCESS;
    }
}
