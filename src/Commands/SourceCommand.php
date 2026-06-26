<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseKeyResolver;

final class SourceCommand extends Command
{
    protected $signature = 'laranail::license-verifier.source {--json}';

    protected $description = 'Show where the license key/details are resolved from';

    /** @var list<string> */
    protected array $commandAliases = ['license:source'];

    public function handle(LicenseKeyResolver $resolver): int
    {
        $key = $resolver->resolve();

        $data = [
            'source' => (string) config('license-verifier.source', 'config'),
            'storage' => (string) config('license-verifier.storage.driver', 'file'),
            'resolved_key' => $this->mask($key),
            'details' => $resolver->details(),
        ];

        if ($this->wantsJson()) {
            $this->renderJson($data);

            return self::SUCCESS;
        }

        $this->services->display()->keyValue([
            'Source' => $data['source'],
            'Storage' => $data['storage'],
            'Resolved key' => $data['resolved_key'] ?? '(none)',
        ], 'License source');

        return self::SUCCESS;
    }

    private function mask(?string $key): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        if (strlen($key) <= 8) {
            return str_repeat('*', strlen($key));
        }

        return substr($key, 0, 4).str_repeat('*', strlen($key) - 8).substr($key, -4);
    }
}
