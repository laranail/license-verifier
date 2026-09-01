<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * "Skip the license reminder for N days" — ported from Botble's
 * Core::skipLicenseReminder. The skip-until timestamp is encrypted at rest.
 */
final readonly class ReminderManager
{
    private string $file;

    public function __construct()
    {
        $base = rtrim((string) config('license-verifier.storage.path', storage_path('app/licensing')), '/');
        $this->file = $base.'/reminder.txt';
    }

    public function skip(?int $days = null): void
    {
        $days ??= (int) config('license-verifier.reminder.default_skip_days', 3);

        $this->ensureDirectory();

        File::put($this->file, encrypt(Carbon::now()->addDays($days)->toIso8601String()));
    }

    public function isSkipped(): bool
    {
        if (! File::exists($this->file)) {
            return false;
        }

        try {
            $until = Carbon::parse(decrypt(File::get($this->file)));

            if (Carbon::now()->lessThanOrEqualTo($until)) {
                return true;
            }

            $this->clear();
        } catch (Throwable) {
            // Corrupt/legacy file — treat as not skipped.
        }

        return false;
    }

    public function skippedUntil(): ?Carbon
    {
        if (! File::exists($this->file)) {
            return null;
        }

        try {
            return Carbon::parse(decrypt(File::get($this->file)));
        } catch (Throwable) {
            return null;
        }
    }

    public function clear(): void
    {
        if (File::exists($this->file)) {
            File::delete($this->file);
        }
    }

    private function ensureDirectory(): void
    {
        $dir = dirname($this->file);

        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
    }
}
