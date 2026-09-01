<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Stores;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseStore;
use Simtabi\Laranail\Licence\Verifier\Models\LicenseRecord;

/**
 * Stores license records in the database via the configured Eloquent model
 * (config('license-verifier.models.license'), default LicenseRecord).
 */
final readonly class DatabaseStore implements LicenseStore
{
    /** @var class-string<Model> */
    private string $model;

    public function __construct(?string $model = null)
    {
        /** @var class-string<Model> $resolved */
        $resolved = $model ?? config('license-verifier.models.license', LicenseRecord::class);
        $this->model = $resolved;
    }

    public function put(string $key, array $data): void
    {
        $this->model::query()->updateOrCreate(['key' => $key], $data);
    }

    public function get(string $key): ?array
    {
        $record = $this->model::query()->where('key', $key)->first();

        return $record?->makeVisible('token')->toArray();
    }

    public function has(string $key): bool
    {
        return $this->model::query()->where('key', $key)->exists();
    }

    public function forget(string $key): void
    {
        $this->model::query()->where('key', $key)->delete();
    }
}
