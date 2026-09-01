<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\LicenseManager;

/**
 * Lists or revokes the seats/machines registered to the active license.
 */
final class SeatsCommand extends Command
{
    protected $signature = 'laranail::license-verifier.seats {action=list : list or revoke} {target? : seat id/fingerprint to revoke} {--json}';

    protected $description = 'List or revoke the seats/machines registered to the license';

    /** @var list<string> */
    protected array $commandAliases = ['license:seats'];

    public function handle(): int
    {
        $manager = $this->laravel->make(LicenseManager::class);

        if (! $manager->supportsSeatManagement()) {
            $this->error('The active driver does not support seat management.');

            return self::FAILURE;
        }

        return match ((string) $this->argument('action')) {
            'list' => $this->listSeats($manager),
            'revoke' => $this->revokeSeat($manager),
            default => $this->unknownAction(),
        };
    }

    private function listSeats(LicenseManager $manager): int
    {
        $seats = $manager->seats();

        if ($this->wantsJson()) {
            $this->renderJson(['seats' => $seats]);

            return self::SUCCESS;
        }

        if ($seats === []) {
            $this->info('No seats are registered to this license.');

            return self::SUCCESS;
        }

        $this->services->display()->displayTable(
            ['ID', 'Fingerprint', 'Last seen', 'Status'],
            array_map(static fn (array $s): array => [
                (string) ($s['id'] ?? '—'),
                (string) ($s['fingerprint'] ?? '—'),
                (string) ($s['last_seen_at'] ?? $s['last_seen'] ?? '—'),
                (string) ($s['status'] ?? '—'),
            ], $seats),
        );

        return self::SUCCESS;
    }

    private function revokeSeat(LicenseManager $manager): int
    {
        $target = (string) $this->argument('target');

        if ($target === '') {
            $this->error('A seat id or fingerprint is required to revoke.');

            return self::FAILURE;
        }

        if ($manager->revokeSeat($target)) {
            $this->info("Seat [{$target}] revoked.");

            return self::SUCCESS;
        }

        $this->error("Failed to revoke seat [{$target}].");

        return self::FAILURE;
    }

    private function unknownAction(): int
    {
        $this->error('Unknown action. Use "list" or "revoke".');

        return self::FAILURE;
    }
}
