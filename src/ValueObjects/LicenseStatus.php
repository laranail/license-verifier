<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\ValueObjects;

/**
 * Normalized, driver-agnostic license status.
 */
enum LicenseStatus: string
{
    case Valid = 'valid';
    case Invalid = 'invalid';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Grace = 'grace';
    case Unactivated = 'unactivated';
    case Unreachable = 'unreachable';

    /**
     * Whether the application should be allowed to run under this status.
     */
    public function isUsable(): bool
    {
        return match ($this) {
            self::Valid, self::Grace => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Invalid => 'Invalid',
            self::Expired => 'Expired',
            self::Revoked => 'Revoked',
            self::Grace => 'Grace period',
            self::Unactivated => 'Not activated',
            self::Unreachable => 'Server unreachable',
        };
    }

    /**
     * Map a raw server status string (active/grace/expired/suspended/cancelled…) onto this enum.
     */
    public static function fromRaw(?string $raw): self
    {
        return match (strtolower((string) $raw)) {
            'active', 'valid' => self::Valid,
            'grace' => self::Grace,
            'expired' => self::Expired,
            'revoked', 'cancelled', 'canceled', 'suspended' => self::Revoked,
            'unactivated', 'pending', '' => self::Unactivated,
            default => self::Invalid,
        };
    }
}
