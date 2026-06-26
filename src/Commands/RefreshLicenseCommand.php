<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Exception;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;

final class RefreshLicenseCommand extends Command
{
    protected $signature = 'laranail::license-verifier.refresh {key? : The license key to refresh}';

    protected $description = 'Refresh a license token';

    /** @var list<string> */
    protected array $commandAliases = ['license:refresh'];

    public function handle(LicenseManager $manager): int
    {
        $licenseKey = $this->argument('key') ?? config('license-verifier.license_key');

        if (! $licenseKey) {
            $this->error('License key is required');

            return self::FAILURE;
        }

        $this->info('Refreshing license token...');

        try {
            if ($manager->refresh($licenseKey)) {
                $this->info('License token refreshed successfully!');

                return self::SUCCESS;
            }

            $this->error('Failed to refresh license token');

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('Token refresh failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
