<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\LicenseManager;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Interactive license-management dashboard. Renders the current status and an
 * action menu, dispatching to the same flows as the standalone commands.
 * Falls back gracefully on non-TTY environments.
 */
final class ManageCommand extends Command
{
    protected $signature = 'laranail::license-verifier.manage';

    protected $description = 'Interactive license management dashboard (TUI)';

    /** @var list<string> */
    protected array $commandAliases = ['license', 'license:manage'];

    public function handle(): int
    {
        if ($this->services->interaction()->isNonInteractive()) {
            $this->call(StatusCommand::class);

            return self::SUCCESS;
        }

        while (true) {
            $this->renderDashboard();

            $action = $this->services->interaction()->askSelect('Choose an action', [
                'activate' => 'Activate a license',
                'validate' => 'Validate the license',
                'refresh' => 'Refresh the token',
                'deactivate' => 'Deactivate the license',
                'drivers' => 'List drivers',
                'doctor' => 'Run diagnostics',
                'quit' => 'Quit',
            ]);

            if ($action === 'quit') {
                return self::SUCCESS;
            }

            $this->dispatch($action);
        }
    }

    private function renderDashboard(): void
    {
        $info = $this->driver()->getLicenseInfo();

        $this->services->display()->header('License Verifier');
        $this->services->display()->keyValue(array_filter([
            'Driver' => $this->manager()->getDefaultDriver(),
            'Status' => $info->status->label(),
            'Licensed to' => $info->licensedTo,
            'Expires' => $info->expiresAt,
            'Seats' => $info->seatsTotal !== null ? "{$info->seatsUsed} / {$info->seatsTotal}" : null,
        ], static fn (?string $v): bool => $v !== null && $v !== ''));
    }

    private function dispatch(string $action): void
    {
        match ($action) {
            'activate' => $this->activate(),
            'validate' => $this->call(StatusCommand::class),
            'refresh' => $this->refresh(),
            'deactivate' => $this->deactivate(),
            'drivers' => $this->call(DriversCommand::class),
            'doctor' => $this->call(DoctorCommand::class),
            default => null,
        };
    }

    private function activate(): void
    {
        $fields = $this->driver()->activationFields();
        $key = $this->services->interaction()->askText('License key', required: true);
        $client = null;

        foreach ($fields as $field) {
            if (($field['name'] ?? null) === 'client') {
                $client = $this->services->interaction()->askText((string) ($field['label'] ?? 'Buyer'));
            }
        }

        $manager = $this->laravel->make(LicenseManager::class);

        $result = $this->services->interaction()->showSpinner(
            'Activating…',
            fn (): VerificationResult => $manager->activate(new LicenseRequest($key, client: $client)),
        );

        $result->isUsable()
            ? $this->services->display()->success('License activated.')
            : $this->services->display()->error($result->message ?? 'Activation failed.');
    }

    private function refresh(): void
    {
        $this->call('laranail::license-verifier.refresh');
    }

    private function deactivate(): void
    {
        if ($this->services->interaction()->askConfirm('Deactivate the license on this machine?')) {
            $this->laravel->make(LicenseManager::class)->deactivate();
            $this->services->display()->success('License deactivated.');
        }
    }
}
