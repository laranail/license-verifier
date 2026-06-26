<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Exception;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;

final class LicenseInfoCommand extends Command
{
    protected $signature = 'laranail::license-verifier.info {key? : The license key to get info for}';

    protected $description = 'Display license information';

    /** @var list<string> */
    protected array $commandAliases = ['license:info'];

    public function handle(LicenseManager $manager): int
    {
        $licenseKey = $this->argument('key') ?? config('license-verifier.license_key');

        if (! $licenseKey) {
            $this->error('License key is required');

            return self::FAILURE;
        }

        $this->info('Fetching license information...');

        try {
            $licenseInfo = $manager->getLicenseInfo($licenseKey);

            if ($licenseInfo === []) {
                $this->error('No license information available. Please activate the license first.');

                return self::FAILURE;
            }

            $this->newLine();
            $this->info('License Information:');
            $this->newLine();

            $this->table(
                ['Property', 'Value'],
                [
                    ['License Key', substr((string) $licenseKey, 0, 8).'...'],
                    ['Status', $licenseInfo['status'] ?? 'N/A'],
                    ['Licensed To', $licenseInfo['licensed_to'] ?? 'N/A'],
                    ['Activated At', $licenseInfo['activated_at'] ?? 'N/A'],
                    ['Expires At', $licenseInfo['expires_at'] ?? 'Never'],
                    ['Seats', $licenseInfo['seats_total'] ?? 'Unlimited'],
                    ['Domain', $licenseInfo['domain'] ?? 'N/A'],
                ]
            );

            if ($manager->isExpiringSoon(7, $licenseKey)) {
                $this->newLine();
                $this->warn('Warning: License is expiring soon!');
            }

            if ($manager->requiresOnlineRefresh($licenseKey)) {
                $this->newLine();
                $this->warn('Warning: Online refresh is required!');
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error('Failed to get license info: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
