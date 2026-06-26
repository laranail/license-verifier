<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Exception;
use Simtabi\Laranail\Licence\Verifier\LicenseManager;

final class ActivateLicenseCommand extends Command
{
    protected $signature = 'laranail::license-verifier.activate {key? : The license key to activate}';

    protected $description = 'Activate a license key';

    /** @var list<string> */
    protected array $commandAliases = ['license:activate'];

    public function handle(LicenseManager $manager): int
    {
        $licenseKey = $this->argument('key') ?? config('license-verifier.license_key');

        if (! $licenseKey) {
            $licenseKey = $this->ask('Please enter your license key');
        }

        if (! $licenseKey) {
            $this->error('License key is required');

            return self::FAILURE;
        }

        $this->info('Activating license...');

        try {
            if ($manager->activate($licenseKey)->isUsable()) {
                $this->info('License activated successfully!');

                $info = $manager->getLicenseInfo($licenseKey);
                if ($info !== []) {
                    $this->table(
                        ['Property', 'Value'],
                        [
                            ['Status', $info['status'] ?? 'N/A'],
                            ['Licensed to', $info['licensed_to'] ?? '—'],
                            ['Expires', $info['expires_at'] ?? 'Never'],
                            ['Seats', $info['seats_total'] ?? 'Unlimited'],
                        ]
                    );
                }

                return self::SUCCESS;
            }

            $this->error('Failed to activate license');

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('Activation failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
