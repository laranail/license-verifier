<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Exception;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;

final class ValidateLicenseCommand extends Command
{
    protected $signature = 'laranail::license-verifier.validate {key? : The license key to validate}';

    protected $description = 'Validate a license';

    /** @var list<string> */
    protected array $commandAliases = ['license:validate'];

    public function handle(LicenseManager $manager): int
    {
        $licenseKey = $this->argument('key') ?? config('license-verifier.license_key');

        if (! $licenseKey) {
            $this->error('License key is required');

            return self::FAILURE;
        }

        $this->info('Validating license...');

        try {
            if ($manager->isValid($licenseKey)) {
                $this->info('✓ License is valid');

                if ($manager->isExpiringSoon(7, $licenseKey)) {
                    $this->warn('⚠ License is expiring soon!');
                }

                return self::SUCCESS;
            }

            $this->error('✗ License is invalid');

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('Validation failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
