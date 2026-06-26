<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\Support\ConnectionChecker;

/**
 * Connection pre-check against the active license source.
 */
final class PingCommand extends Command
{
    protected $signature = 'laranail::license-verifier.ping {--fresh : Bypass the cached result} {--json}';

    protected $description = 'Check whether the license server is reachable';

    /** @var list<string> */
    protected array $commandAliases = ['license:ping', 'license:check-connection'];

    public function handle(ConnectionChecker $connection): int
    {
        $fresh = (bool) $this->option('fresh');

        $reachable = $this->services->interaction()->showSpinner(
            'Checking license server…',
            static fn (): bool => $connection->check($fresh),
        );

        if ($this->wantsJson()) {
            $this->renderJson(['reachable' => $reachable]);
        } elseif ($reachable) {
            $this->services->display()->success('License server is reachable.');
        } else {
            $this->services->display()->error('License server is not reachable.');
        }

        return $reachable ? self::SUCCESS : 2;
    }
}
