<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Override;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Whop.com membership licensing — POST /v2/memberships/{key}/validate.
 *
 * A "license key" is a membership id; validation confirms the membership is
 * `active` or `trialing`. There is no remote deactivation endpoint (memberships
 * are cancelled in the Whop dashboard), so deactivate() only clears the local record.
 *
 * @see https://docs.whop.com/developer/api/getting-started
 */
final class WhopDriver extends AbstractHttpDriver
{
    public function name(): string
    {
        return 'whop';
    }

    #[Override]
    public function activationFields(): array
    {
        return [
            ['name' => 'license_key', 'label' => 'Membership / license key', 'type' => 'text', 'required' => true],
            ['name' => 'client', 'label' => 'Buyer', 'type' => 'text', 'required' => false],
        ];
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return $this->validateMembership($request->key, $request->client);
    }

    public function verify(?string $key = null): VerificationResult
    {
        return $this->validateMembership($key ?? (string) config('license-verifier.license_key'), null);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $this->store()->forget($key ?? (string) config('license-verifier.license_key'));

        return true;
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        return $this->http($this->auth())->get('/v2/memberships')->status() !== 0;
    }

    private function validateMembership(string $key, ?string $client): VerificationResult
    {
        $response = $this->http($this->auth())->post("/v2/memberships/{$key}/validate");
        $data = (array) $response->json();

        if (! $response->successful()) {
            return VerificationResult::invalid(LicenseStatus::Invalid, message: $data['message'] ?? 'License key is invalid.', raw: $data);
        }

        $status = (string) ($data['status'] ?? '');

        if (! in_array($status, ['active', 'trialing'], true)) {
            return VerificationResult::invalid(LicenseStatus::Expired, message: 'Membership is not active.', raw: $data);
        }

        $licensedTo = (string) ($data['user']['username'] ?? $data['user']['email'] ?? $client ?? '');

        $this->remember($key, [
            'licensed_to' => $licensedTo,
            'status' => 'active',
            'token' => (string) ($data['id'] ?? $key),
        ]);

        return VerificationResult::valid(
            status: $status === 'trialing' ? LicenseStatus::Grace : LicenseStatus::Valid,
            licensedTo: $licensedTo !== '' ? $licensedTo : null,
            raw: $data,
        );
    }

    /**
     * @return array<string, string>
     */
    private function auth(): array
    {
        $apiKey = (string) $this->cfg('api_key', '');

        return $apiKey !== '' ? ['Authorization' => "Bearer {$apiKey}"] : [];
    }
}
