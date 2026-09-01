<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Exception;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;

final class DeactivateLicenseCommand extends Command
{
    protected $signature = 'laranail::license-verifier.deactivate {key? : The license key to deactivate}';

    protected $description = 'Deactivate a license key';

    /** @var list<string> */
    protected array $commandAliases = ['license:deactivate'];

    public function handle(LicenseManager $manager): int
    {
        $licenseKey = $this->argument('key') ?? config('license-verifier.license_key');

        if (! $licenseKey) {
            $this->error('License key is required');

            return self::FAILURE;
        }

        if (! $this->confirm('Are you sure you want to deactivate this license?')) {
            $this->info('Deactivation cancelled');

            return self::SUCCESS;
        }

        $this->info('Deactivating license...');

        try {
            if ($manager->deactivate($licenseKey)) {
                $this->info('License deactivated successfully!');

                return self::SUCCESS;
            }

            $this->error('Failed to deactivate license');

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('Deactivation failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
