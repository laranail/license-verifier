<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Stores;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
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
        } catch (Throwable) {
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
