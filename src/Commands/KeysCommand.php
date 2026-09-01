<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\Services\TokenStorage;

/**
 * Inspect the configured public key and the stored key bundle (rotation).
 */
final class KeysCommand extends Command
{
    protected $signature = 'laranail::license-verifier.keys {--json}';

    protected $description = 'Show the configured public key and stored key bundle';

    /** @var list<string> */
    protected array $commandAliases = ['license:keys'];

    public function handle(TokenStorage $storage): int
    {
        $public = (string) config('license-verifier.public_key');
        $bundle = $storage->getPublicKeyBundle() ?? [];

        $data = [
            'public_key' => $this->fingerprint($public),
            'bundle_signing_kid' => $bundle['signing']['kid'] ?? null,
            'bundle_root_kid' => $bundle['root']['kid'] ?? null,
        ];

        if ($this->wantsJson()) {
            $this->renderJson($data);

            return self::SUCCESS;
        }

        $this->services->display()->keyValue(array_filter([
            'Public key' => $data['public_key'] ?: '(none configured)',
            'Signing kid' => $data['bundle_signing_kid'],
            'Root kid' => $data['bundle_root_kid'],
        ]), 'Keys');

        return self::SUCCESS;
    }

    private function fingerprint(string $key): string
    {
        return $key === '' ? '' : substr(hash('sha256', $key), 0, 16);
    }
}
