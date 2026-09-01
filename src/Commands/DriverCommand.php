<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

final class DriverCommand extends Command
{
    protected $signature = 'laranail::license-verifier.driver {name? : Driver to inspect} {--test : Ping the driver health endpoint} {--json}';

    protected $description = 'Inspect (and optionally health-test) a license driver';

    /** @var list<string> */
    protected array $commandAliases = ['license:driver'];

    public function handle(): int
    {
        $name = (string) ($this->argument('name') ?: $this->manager()->getDefaultDriver());
        $driver = $this->manager()->driver($name);

        $data = [
            'driver' => $name,
            'capabilities' => $driver->capabilities(),
            'activation_fields' => array_column($driver->activationFields(), 'name'),
        ];

        $reachable = null;

        if ($this->option('test')) {
            $reachable = $this->services->interaction()->showSpinner(
                "Testing {$name}…",
                static fn (): bool => rescue(static fn (): bool => $driver->health(), false) ?: false,
            );
            $data['reachable'] = $reachable;
        }

        if ($this->wantsJson()) {
            $this->renderJson($data);
        } else {
            $this->services->display()->keyValue([
                'Driver' => $name,
                'Capabilities' => implode(', ', $driver->capabilities()) ?: '—',
            ]);

            if ($reachable !== null) {
                $reachable
                    ? $this->services->display()->success('Driver is reachable.')
                    : $this->services->display()->error('Driver is not reachable.');
            }
        }

        return ($this->option('test') && $reachable === false) ? 2 : self::SUCCESS;
    }
}
