<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\LicenceVerifier;

final class ClearCommand extends Command
{
    protected $signature = 'laranail::license-verifier.clear {--force : Skip confirmation}';

    protected $description = 'Wipe all locally stored license data';

    /** @var list<string> */
    protected array $commandAliases = ['license:clear'];

    public function handle(LicenceVerifier $engine, LicenseStore $store): int
    {
        if (! $this->option('force') && ! $this->services->interaction()->askConfirm('Wipe all stored license data?')) {
            return self::SUCCESS;
        }

        $engine->clearAll();

        $key = config('license-verifier.license_key');

        if ($key) {
            $store->forget((string) $key);
        }

        $this->services->display()->success('License data cleared.');

        return self::SUCCESS;
    }
}
