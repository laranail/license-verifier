<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Commands;

use Simtabi\Laranail\Licence\Verifier\Support\ReminderManager;

/**
 * Manage the "skip license reminder for N days" flag (skip|clear|status).
 */
final class ReminderCommand extends Command
{
    protected $signature = 'laranail::license-verifier.reminder {action=status : skip|clear|status} {--days= : Days to skip}';

    protected $description = 'Skip, clear or inspect the license reminder';

    /** @var list<string> */
    protected array $commandAliases = ['license:reminder'];

    public function handle(ReminderManager $reminder): int
    {
        $action = (string) $this->argument('action');

        return match ($action) {
            'skip' => $this->skip($reminder),
            'clear' => $this->clear($reminder),
            'status' => $this->status($reminder),
            default => $this->invalid($action),
        };
    }

    private function skip(ReminderManager $reminder): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;
        $reminder->skip($days);

        $this->services->display()->success(
            'License reminder skipped until '.($reminder->skippedUntil()?->toDayDateTimeString() ?? 'later').'.',
        );

        return self::SUCCESS;
    }

    private function clear(ReminderManager $reminder): int
    {
        $reminder->clear();
        $this->services->display()->success('License reminder cleared.');

        return self::SUCCESS;
    }

    private function status(ReminderManager $reminder): int
    {
        $reminder->isSkipped()
            ? $this->services->display()->info('Reminder is skipped until '.$reminder->skippedUntil()?->toDayDateTimeString().'.')
            : $this->services->display()->info('Reminder is active (not skipped).');

        return self::SUCCESS;
    }

    private function invalid(string $action): int
    {
        $this->services->display()->error("Unknown action [{$action}]. Use skip, clear or status.");

        return self::FAILURE;
    }
}
