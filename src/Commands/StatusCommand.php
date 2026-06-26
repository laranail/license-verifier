<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;

/**
 * Terse license status with CI/healthcheck-friendly exit codes:
 *   0 valid/grace · 1 invalid/expired/unactivated · 2 server unreachable
 * (unreachable maps to 0 within grace unless --strict).
 */
final class StatusCommand extends Command
{
    protected $signature = 'laranail::license-verifier.status {--strict : Treat unreachable/grace as failure} {--json}';

    protected $description = 'Show the current license status (exit code reflects validity)';

    /** @var list<string> */
    protected array $commandAliases = ['license:status'];

    public function handle(): int
    {
        $result = $this->driver()->verify();

        if ($this->wantsJson()) {
            $this->renderJson($result->toArray());
        } else {
            $line = $result->status->label();
            $result->isUsable()
                ? $this->services->display()->success("License status: {$line}")
                : $this->services->display()->error("License status: {$line}");
        }

        $strict = (bool) $this->option('strict');

        return match (true) {
            $result->status === LicenseStatus::Valid => self::SUCCESS,
            $result->status === LicenseStatus::Grace => $strict ? self::FAILURE : self::SUCCESS,
            $result->status === LicenseStatus::Unreachable => $strict ? 2 : self::SUCCESS,
            default => self::FAILURE,
        };
    }
}
