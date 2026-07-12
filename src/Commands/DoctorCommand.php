<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\Doctor\Checks;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorReporter;

/**
 * Diagnoses common license-verifier misconfiguration.
 */
final class DoctorCommand extends Command
{
    protected $signature = 'laranail::license-verifier.doctor {--json} {--strict : Fail on warnings too}';

    protected $description = 'Run diagnostics on the license-verifier configuration';

    /** @var list<string> */
    protected array $commandAliases = ['license:doctor'];

    public function handle(): int
    {
        return DoctorReporter::render($this, Checks::all(), $this->wantsJson(), (bool) $this->option('strict'));
    }
}
