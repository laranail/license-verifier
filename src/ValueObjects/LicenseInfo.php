<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\ValueObjects;

/**
 * Normalized, presentable license details across all drivers.
 */
final readonly class LicenseInfo
{
    /**
     * @param  array<string, mixed>  $entitlements
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public LicenseStatus $status,
        public ?string $licensedTo = null,
        public ?string $activatedAt = null,
        public ?string $expiresAt = null,
        public ?int $seatsUsed = null,
        public ?int $seatsTotal = null,
        public ?string $domain = null,
        public ?string $fingerprint = null,
        public array $entitlements = [],
        public array $raw = [],
    ) {}

    public static function empty(): self
    {
        return new self(LicenseStatus::Unactivated);
    }

    /**
     * Build from the engine's flat license-info array (PASETO shape) with sensible fallbacks.
     *
     * @param  array<string, mixed>  $info
     */
    public static function fromArray(array $info): self
    {
        if ($info === []) {
            return self::empty();
        }

        return new self(
            status: LicenseStatus::fromRaw($info['status'] ?? null),
            licensedTo: $info['licensed_to'] ?? $info['licensee'] ?? null,
            activatedAt: $info['activated_at'] ?? $info['issued_at'] ?? null,
            expiresAt: $info['license_expires_at'] ?? $info['expires_at'] ?? null,
            seatsUsed: isset($info['seats_used']) ? (int) $info['seats_used'] : null,
            seatsTotal: isset($info['max_usages']) && $info['max_usages'] !== -1 ? (int) $info['max_usages'] : null,
            domain: $info['domain'] ?? null,
            fingerprint: $info['usage_fingerprint'] ?? $info['fingerprint'] ?? null,
            entitlements: (array) ($info['entitlements'] ?? []),
            raw: $info,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'licensed_to' => $this->licensedTo,
            'activated_at' => $this->activatedAt,
            'expires_at' => $this->expiresAt,
            'seats_used' => $this->seatsUsed,
            'seats_total' => $this->seatsTotal,
            'domain' => $this->domain,
            'fingerprint' => $this->fingerprint,
            'entitlements' => $this->entitlements,
        ];
    }
}
