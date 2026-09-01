<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

/**
 * Live status dashboard — refreshes the license status on an interval.
 * On a non-interactive shell it renders a single snapshot and exits.
 */
final class WatchCommand extends Command
{
    protected $signature = 'laranail::license-verifier.watch {--interval=5 : Seconds between refreshes} {--cycles= : Stop after N refreshes (testing)}';

    protected $description = 'Live license status dashboard';

    /** @var list<string> */
    protected array $commandAliases = ['license:watch'];

    public function handle(): int
    {
        $interval = max(1, (int) $this->option('interval'));
        $cycles = $this->option('cycles') !== null ? (int) $this->option('cycles') : null;

        if ($this->services->interaction()->isNonInteractive() && $cycles === null) {
            $this->renderSnapshot();

            return self::SUCCESS;
        }

        $count = 0;

        while (true) {
            $this->renderSnapshot();

            if (++$count === $cycles) {
                break;
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }

    private function renderSnapshot(): void
    {
        $info = $this->driver()->getLicenseInfo();

        $this->services->display()->header('License — '.now()->toTimeString());
        $this->services->display()->keyValue(array_filter([
            'Driver' => $this->manager()->getDefaultDriver(),
            'Status' => $info->status->label(),
            'Licensed to' => $info->licensedTo,
            'Expires' => $info->expiresAt,
            'Seats' => $info->seatsTotal !== null ? "{$info->seatsUsed} / {$info->seatsTotal}" : null,
        ], static fn (?string $v): bool => $v !== null && $v !== ''));
    }
}
