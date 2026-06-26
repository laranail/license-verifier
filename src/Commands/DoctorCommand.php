<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\Doctor\DriverResolvesCheck;
use Simtabi\Laranail\Licence\Verifier\Doctor\LicenseKeyCheck;
use Simtabi\Laranail\Licence\Verifier\Doctor\PublicKeyCheck;
use Simtabi\Laranail\Licence\Verifier\Doctor\ServerReachableCheck;
use Simtabi\Laranail\Licence\Verifier\Doctor\SodiumExtensionCheck;
use Simtabi\Laranail\Licence\Verifier\Doctor\StorageBackendCheck;
use Simtabi\Laranail\Licence\Verifier\Doctor\StorageWritableCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorService;

/**
 * Diagnoses common license-verifier misconfiguration.
 */
final class DoctorCommand extends Command
{
    protected $signature = 'laranail::license-verifier.doctor {--json} {--strict : Fail on warnings too}';

    protected $description = 'Run diagnostics on the license-verifier configuration';

    /** @var list<string> */
    protected array $commandAliases = ['license:doctor'];

    /**
     * The canonical verifier health checks — reused by the service provider to
     * feed the unified package-tools doctor and by the HTTP health endpoint.
     *
     * @var list<class-string<DoctorCheck>>
     */
    public const array CHECKS = [
        DriverResolvesCheck::class,
        LicenseKeyCheck::class,
        PublicKeyCheck::class,
        StorageWritableCheck::class,
        StorageBackendCheck::class,
        SodiumExtensionCheck::class,
        ServerReachableCheck::class,
    ];

    public function handle(): int
    {
        $service = new DoctorService;

        foreach (self::CHECKS as $check) {
            $service->register($check);
        }

        $report = $service->run();
        $summary = $service->summarise($report);

        if ($this->wantsJson()) {
            $this->renderJson([
                'checks' => array_map(static fn (array $row): array => [
                    'name' => $row['check']->name(),
                    'status' => $row['result']->status->value,
                    'message' => $row['result']->message,
                    'detail' => $row['result']->detail,
                ], $report),
            ]);
        } else {
            $this->services->display()->displayTable(
                ['Check', 'Status', 'Detail'],
                array_map(static fn (array $row): array => [
                    $row['check']->name(),
                    strtoupper($row['result']->status->value),
                    $row['result']->message,
                ], $report),
            );
        }

        return ($summary['fail'] > 0 || ((bool) $this->option('strict') && $summary['warn'] > 0))
            ? self::FAILURE
            : self::SUCCESS;
    }
}
