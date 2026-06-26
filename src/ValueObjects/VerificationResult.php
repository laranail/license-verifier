<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\ValueObjects;

/**
 * The normalized outcome of an activate/verify call across all drivers.
 */
final readonly class VerificationResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public LicenseStatus $status,
        public bool $valid,
        public ?string $message = null,
        public ?string $licensedTo = null,
        public ?string $activatedAt = null,
        public ?string $expiresAt = null,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function valid(
        LicenseStatus $status = LicenseStatus::Valid,
        ?string $message = null,
        ?string $licensedTo = null,
        ?string $activatedAt = null,
        ?string $expiresAt = null,
        array $raw = [],
    ): self {
        return new self($status, $status->isUsable(), $message, $licensedTo, $activatedAt, $expiresAt, $raw);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function invalid(
        LicenseStatus $status = LicenseStatus::Invalid,
        ?string $message = null,
        array $raw = [],
    ): self {
        return new self($status, false, $message, raw: $raw);
    }

    public function isUsable(): bool
    {
        return $this->valid && $this->status->isUsable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'valid' => $this->valid,
            'message' => $this->message,
            'licensed_to' => $this->licensedTo,
            'activated_at' => $this->activatedAt,
            'expires_at' => $this->expiresAt,
        ];
    }
}
