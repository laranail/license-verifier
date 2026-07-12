<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseKeyResolver;
use Simtabi\Laranail\Licence\Verifier\Services\TokenStorage;

/**
 * Offline token operations for air-gapped activation (show|export|import).
 */
final class TokenCommand extends Command
{
    protected $signature = 'laranail::license-verifier.token {action=show : show|export|import} {path? : File for export/import} {--json}';

    protected $description = 'Show, export or import the offline license token';

    /** @var list<string> */
    protected array $commandAliases = ['license:token'];

    public function handle(TokenStorage $storage, LicenseKeyResolver $resolver): int
    {
        $key = $resolver->resolve() ?? (string) config('license-verifier.license_key');

        if ($key === '') {
            $this->services->display()->error('No license key resolved. Configure license-verifier.license_key or source.');

            return self::FAILURE;
        }

        return match ((string) $this->argument('action')) {
            'export' => $this->export($storage, $key),
            'import' => $this->import($storage, $key),
            default => $this->show($storage, $key),
        };
    }

    private function show(TokenStorage $storage, string $key): int
    {
        $token = $storage->retrieve($key);

        if (! $token) {
            $this->services->display()->error('No token stored for this license.');

            return self::FAILURE;
        }

        if ($this->wantsJson()) {
            $this->renderJson(['present' => true, 'length' => strlen($token), 'info' => $this->engine()->getLicenseInfo($key)]);

            return self::SUCCESS;
        }

        $this->services->display()->success('A token is stored ('.strlen($token).' bytes).');
        $this->services->display()->keyValue($this->engine()->getLicenseInfo($key));

        return self::SUCCESS;
    }

    private function export(TokenStorage $storage, string $key): int
    {
        $token = $storage->retrieve($key);

        if (! $token) {
            $this->services->display()->error('No token stored to export.');

            return self::FAILURE;
        }

        $path = (string) ($this->argument('path') ?: getcwd().'/license.token');
        File::put($path, $token);

        $this->services->display()->success("Token exported to {$path}");

        return self::SUCCESS;
    }

    private function import(TokenStorage $storage, string $key): int
    {
        $path = (string) $this->argument('path');

        if ($path === '' || ! File::exists($path)) {
            $this->services->display()->error("Token file not found: {$path}");

            return self::FAILURE;
        }

        $storage->store(trim(File::get($path)), $key);

        $this->services->display()->success('Token imported. Run "license:validate" to confirm.');

        return self::SUCCESS;
    }
}
