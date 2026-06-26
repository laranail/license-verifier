<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Simtabi\Laranail\Licence\Verifier\Models\LicenseRecord;

/**
 * @extends Factory<LicenseRecord>
 */
class LicenseRecordFactory extends Factory
{
    protected $model = LicenseRecord::class;

    public function definition(): array
    {
        return [
            'key' => strtoupper($this->faker->bothify('????????-????????-????????-????????')),
            'driver' => 'paseto',
            'token' => null,
            'fingerprint' => hash('sha256', $this->faker->uuid()),
            'domain' => $this->faker->domainName(),
            'licensed_to' => $this->faker->name(),
            'status' => 'active',
            'activated_at' => now(),
            'last_validated_at' => now(),
            'last_heartbeat_at' => now(),
            'expires_at' => now()->addYear(),
            'metadata' => [],
        ];
    }
}
