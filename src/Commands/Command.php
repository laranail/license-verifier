<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Console\Tools\Commands\Command as ConsoleCommand;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Licence\Verifier\Contracts\Driver;
use Simtabi\Laranail\Licence\Verifier\Drivers\DriverManager;
use Simtabi\Laranail\Licence\Verifier\LicenceVerifier;

/**
 * Base command for the license-verifier CLI/TUI suite. Provides the laranail
 * `::`-namespaced naming, the active driver/manager/engine accessors and a
 * `--json` output helper.
 */
abstract class Command extends ConsoleCommand
{
    use SupportsNamespacedNames;

    protected function manager(): DriverManager
    {
        return $this->laravel->make(DriverManager::class);
    }

    protected function driver(): Driver
    {
        return $this->manager()->active();
    }

    protected function engine(): LicenceVerifier
    {
        return $this->laravel->make(LicenceVerifier::class);
    }

    protected function wantsJson(): bool
    {
        return $this->input->hasOption('json') && (bool) $this->input->getOption('json');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function renderJson(array $data): void
    {
        $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
