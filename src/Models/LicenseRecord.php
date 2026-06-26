<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;
use Simtabi\Laranail\Licence\Verifier\Database\Factories\LicenseRecordFactory;

/**
 * Local record of an activated license (used by the database store/resolver).
 *
 * Swap this model via config('license-verifier.models.license').
 *
 * @property string $key
 * @property string|null $driver
 * @property string|null $token
 * @property string|null $fingerprint
 * @property string|null $domain
 * @property string|null $licensed_to
 * @property string|null $status
 * @property Carbon|null $activated_at
 * @property Carbon|null $last_validated_at
 * @property Carbon|null $last_heartbeat_at
 * @property Carbon|null $expires_at
 * @property array<string, mixed>|null $metadata
 */
class LicenseRecord extends Model
{
    /** @use HasFactory<LicenseRecordFactory> */
    use HasFactory;

    protected $table = 'license_verifier_licenses';

    protected $guarded = [];

    /**
     * The encrypted token is hidden from array/JSON output by default.
     *
     * @var list<string>
     */
    protected $hidden = ['token'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'metadata' => 'encrypted:array',
            'activated_at' => 'datetime',
            'last_validated_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function newFactory(): LicenseRecordFactory
    {
        return LicenseRecordFactory::new();
    }
}
