<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Stores;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Package\Tools\Support\Resilience\FailurePolicy;
use Throwable;

/**
 * Stores license records as encrypted JSON files under the configured path.
 */
final readonly class FileStore implements LicenseStore
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = rtrim((string) ($path ?? config('license-verifier.storage.path', storage_path('app/licensing'))), '/').'/records';

        if (! File::isDirectory($this->path)) {
            File::makeDirectory($this->path, 0700, true);
        }
    }

    public function put(string $key, array $data): void
    {
        // Atomic (temp file + rename) with owner-only perms — defense in depth on
        // top of encryption, and crash-safe against torn/truncated records.
        File::replace($this->filename($key), encrypt(json_encode($data)), 0600);
    }

    public function get(string $key): ?array
    {
        $file = $this->filename($key);

        if (! File::exists($file)) {
            return null;
        }

        try {
            return json_decode((string) decrypt(File::get($file)), true);
        } catch (Throwable $e) {
            // A tolerated anomaly (failure-handling standard, rule 14): the file
            // exists but won't decrypt (corruption, tampering, or a rotated app
            // key). We honour the "no value" contract by returning null, but a
            // present-yet-unreadable record is worth surfacing before it silently
            // forces a re-fetch. Context is redacted (rule 15): the hashed record
            // id and exception class only — never the ciphertext or decrypted data.
            FailurePolicy::warn('license record could not be decrypted', [
                'store' => 'file',
                'record' => basename($file, '.json'),
                'reason' => 'threw '.$e::class,
                'decision' => 'treated as absent (returned null)',
            ]);

            return null;
        }
    }

    public function has(string $key): bool
    {
        return File::exists($this->filename($key));
    }

    public function forget(string $key): void
    {
        $file = $this->filename($key);

        if (File::exists($file)) {
            File::delete($file);
        }
    }

    private function filename(string $key): string
    {
        return $this->path.'/'.hash('sha256', $key).'.json';
    }
}
