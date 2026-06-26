<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Stores\FallbackLicenseStore;
use Throwable;

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
        $checks = $this->runChecks();

        if ($this->wantsJson()) {
            $this->renderJson(['checks' => $checks]);
        } else {
            $this->services->display()->displayTable(
                ['Check', 'Status', 'Detail'],
                array_map(static fn (array $c): array => [$c['name'], strtoupper($c['status']), $c['detail']], $checks),
            );
        }

        $hasFail = array_any($checks, static fn (array $c): bool => $c['status'] === 'fail');
        $hasWarn = array_any($checks, static fn (array $c): bool => $c['status'] === 'warn');

        return ($hasFail || ($this->option('strict') && $hasWarn)) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array{name: string, status: string, detail: string}>
     */
    private function runChecks(): array
    {
        $driverName = $this->manager()->getDefaultDriver();
        $storagePath = (string) config('license-verifier.storage.path', storage_path('app/licensing'));

        $driverResolvable = true;
        $driverDetail = "driver: {$driverName}";

        try {
            $this->driver();
        } catch (Throwable $e) {
            $driverResolvable = false;
            $driverDetail = $e->getMessage();
        }

        return [
            $this->check('Driver resolves', $driverResolvable ? 'pass' : 'fail', $driverDetail),
            $this->check(
                'License key configured',
                config('license-verifier.license_key') ? 'pass' : 'warn',
                config('license-verifier.license_key') ? 'present' : 'no LICENSE_VERIFIER_KEY set',
            ),
            $this->check(
                'Public key configured',
                config('license-verifier.public_key') ? 'pass' : ($driverName === 'paseto' ? 'warn' : 'pass'),
                config('license-verifier.public_key') ? 'present' : 'recommended for offline PASETO verification',
            ),
            $this->check(
                'Storage writable',
                $this->storageWritable($storagePath) ? 'pass' : 'fail',
                $storagePath,
            ),
            $this->storageCheck(),
            $this->check('Sodium extension', extension_loaded('sodium') ? 'pass' : 'fail', 'required for crypto'),
        ];
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function storageCheck(): array
    {
        $driver = (string) config('license-verifier.storage.driver', 'file');
        $fallback = config('license-verifier.storage.fallback');

        $store = $this->laravel->make(LicenseStore::class);

        if ($store instanceof FallbackLicenseStore) {
            $pending = $store->pendingSyncCount();

            if ($store->onFallback()) {
                return $this->check('Storage backend', 'warn', "{$driver} unreachable — serving local fallback ({$pending} pending sync)");
            }

            return $this->check('Storage backend', 'pass', "{$driver} (primary) + {$fallback} fallback".($pending > 0 ? " — {$pending} pending sync" : ''));
        }

        return $this->check('Storage backend', 'pass', $driver);
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function check(string $name, string $status, string $detail): array
    {
        return ['name' => $name, 'status' => $status, 'detail' => $detail];
    }

    private function storageWritable(string $path): bool
    {
        if (! File::isDirectory($path)) {
            try {
                File::makeDirectory($path, 0755, true);
            } catch (Throwable) {
                return false;
            }
        }

        return File::isWritable($path);
    }
}
