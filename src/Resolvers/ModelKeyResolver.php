<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Licence\Verifier\Contracts\LicenseKeyResolver;
use Simtabi\Laranail\Licence\Verifier\Models\LicenseRecord;

/**
 * Resolves the license key from the most recently activated database record.
 */
final readonly class ModelKeyResolver implements LicenseKeyResolver
{
    /** @var class-string<Model> */
    private string $model;

    public function __construct(?string $model = null)
    {
        /** @var class-string<Model> $resolved */
        $resolved = $model ?? config('license-verifier.models.license', LicenseRecord::class);
        $this->model = $resolved;
    }

    public function resolve(): ?string
    {
        return $this->record()?->getAttribute('key');
    }

    public function details(): array
    {
        $record = $this->record();

        if (! $record instanceof Model) {
            return [];
        }

        return array_filter([
            'licensed_to' => $record->getAttribute('licensed_to'),
            'domain' => $record->getAttribute('domain'),
            'status' => $record->getAttribute('status'),
            'activated_at' => optional($record->getAttribute('activated_at'))?->toIso8601String(),
            'expires_at' => optional($record->getAttribute('expires_at'))?->toIso8601String(),
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private function record(): ?Model
    {
        return $this->model::query()->latest('activated_at')->first();
    }
}
