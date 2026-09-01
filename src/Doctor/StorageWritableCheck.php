<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Doctor;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorCheck;
use Simtabi\Laranail\Package\Tools\Services\Doctor\DoctorResult;
use Throwable;

/**
 * The license storage path must be writable.
 */
final class StorageWritableCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'license-verifier:storage-writable';
    }

    public function description(): string
    {
        return 'The license storage path is writable';
    }

    public function run(): DoctorResult
    {
        $path = (string) config('license-verifier.storage.path', storage_path('app/licensing'));

        if (! File::isDirectory($path)) {
            try {
                File::makeDirectory($path, 0755, true);
            } catch (Throwable) {
                return DoctorResult::fail("Not writable: {$path}");
            }
        }

        return File::isWritable($path)
            ? DoctorResult::pass($path)
            : DoctorResult::fail("Not writable: {$path}");
    }
}
