<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\ValueObjects;

/**
 * A normalized activation/verification request handed to a driver.
 */
final readonly class LicenseRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public string $key,
        public ?string $client = null,
        public ?string $fingerprint = null,
        public array $metadata = [],
        public array $extra = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) ($data['key'] ?? $data['license_key'] ?? $data['purchase_code'] ?? ''),
            client: $data['client'] ?? $data['buyer'] ?? $data['licensed_to'] ?? null,
            fingerprint: $data['fingerprint'] ?? null,
            metadata: (array) ($data['metadata'] ?? []),
            extra: $data['extra'] ?? [],
        );
    }
}
