<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Throwable;

/**
 * Lists the available license drivers and their capabilities.
 */
final class DriversCommand extends Command
{
    protected $signature = 'laranail::license-verifier.drivers {--json}';

    protected $description = 'List available license drivers and their capabilities';

    /** @var list<string> */
    protected array $commandAliases = ['license:drivers'];

    public function handle(): int
    {
        $default = $this->manager()->getDefaultDriver();
        $names = $this->driverNames();

        $rows = [];

        foreach ($names as $name) {
            $capabilities = '—';

            try {
                $capabilities = implode(', ', $this->manager()->driver($name)->capabilities()) ?: '—';
            } catch (Throwable) {
                $capabilities = '(unavailable)';
            }

            $rows[$name] = [
                'driver' => $name,
                'default' => $name === $default ? 'yes' : '',
                'capabilities' => $capabilities,
            ];
        }

        if ($this->wantsJson()) {
            $this->renderJson(['default' => $default, 'drivers' => array_values($rows)]);

            return self::SUCCESS;
        }

        $this->services->display()->displayTable(
            ['Driver', 'Default', 'Capabilities'],
            array_values(array_map(array_values(...), $rows)),
        );

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function driverNames(): array
    {
        $configured = array_keys((array) config('license-verifier.drivers', []));

        return array_values(array_unique(array_merge(['paseto'], $configured)));
    }
}
