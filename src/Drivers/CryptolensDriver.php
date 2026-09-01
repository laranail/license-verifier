<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Simtabi\Laranail\Licence\Verifier\Support\RsaPublicKey;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;
use Throwable;

/**
 * Cryptolens key activation — POST /api/key/Activate. The response is RSA-signed;
 * when a `public_key` (PEM or Cryptolens XML) is configured the signature is
 * verified (fail-closed) before the license is trusted.
 *
 * @see https://app.cryptolens.io/docs/api/v3/Activate
 */
final class CryptolensDriver extends AbstractHttpDriver
{
    public function name(): string
    {
        return 'cryptolens';
    }

    public function activate(LicenseRequest $request): VerificationResult
    {
        return $this->activateKey($request->key, $this->fingerprint($request->fingerprint), persist: true);
    }

    public function verify(?string $key = null): VerificationResult
    {
        return $this->activateKey($key ?? (string) config('license-verifier.license_key'), $this->fingerprint(null), persist: false);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');

        // Release the machine on Cryptolens; local forget happens regardless.
        rescue(fn () => $this->http()->asForm()->post('/api/key/Deactivate', array_filter([
            'token' => $this->cfg('token'),
            'ProductId' => $this->cfg('product_id'),
            'Key' => $key,
            'MachineCode' => $this->fingerprint(null),
        ])), report: false);

        $this->store()->forget($key);

        return true;
    }

    public function getLicenseInfo(?string $key = null): LicenseInfo
    {
        $key ??= (string) config('license-verifier.license_key');

        return LicenseInfo::fromArray((array) $this->store()->get($key));
    }

    public function health(): bool
    {
        return $this->http()->get('/')->status() !== 0;
    }

    private function activateKey(string $key, string $machineCode, bool $persist): VerificationResult
    {
        $response = $this->http()->asForm()->post('/api/key/Activate', array_filter([
            'token' => $this->cfg('token'),
            'ProductId' => $this->cfg('product_id'),
            'Key' => $key,
            'Sign' => 'true',
            'MachineCode' => $machineCode,
        ]));

        $data = (array) $response->json();

        // Cryptolens: result === 0 means success.
        if ((int) ($data['result'] ?? 1) !== 0) {
            return VerificationResult::invalid(LicenseStatus::Invalid, message: $data['message'] ?? null, raw: $data);
        }

        $licenseKey = $data['licenseKey'] ?? null;

        // Verify the RSA-signed response when a public key is configured (fail closed).
        if (is_string($licenseKey) && ! $this->verifySignature($licenseKey, (string) ($data['signature'] ?? ''))) {
            return VerificationResult::invalid(
                LicenseStatus::Invalid,
                message: 'Cryptolens response signature verification failed.',
                raw: $data,
            );
        }

        $license = $this->decodeLicense($licenseKey);
        $expires = $license['Expires'] ?? null;

        if ($persist) {
            $this->remember($key, [
                'status' => 'active',
                'expires_at' => $expires,
                'metadata' => ['features' => $license['F1'] ?? null, 'signature' => $data['signature'] ?? null],
            ]);
        }

        return VerificationResult::valid(expiresAt: $expires, raw: $data);
    }

    /**
     * Verify base64(signature) over the base64(licenseKey) bytes with the
     * configured RSA public key. Returns true (skip) when no key is configured.
     */
    private function verifySignature(string $licenseKeyB64, string $signatureB64): bool
    {
        $pem = RsaPublicKey::toPem((string) $this->cfg('public_key'));

        if ($pem === null) {
            return true; // no key configured — verification not enforced (documented)
        }

        $payload = base64_decode($licenseKeyB64, true);
        $signature = base64_decode($signatureB64, true);

        if ($payload === false || $signature === false || $signature === '') {
            return false;
        }

        try {
            $publicKey = openssl_pkey_get_public($pem);

            return $publicKey !== false
                && openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The Cryptolens `licenseKey` is base64 of the license JSON; fall back to an
     * already-decoded array (e.g. test fixtures).
     *
     * @return array<string, mixed>
     */
    private function decodeLicense(mixed $licenseKey): array
    {
        if (is_array($licenseKey)) {
            return $licenseKey;
        }

        if (is_string($licenseKey) && $licenseKey !== '') {
            $decoded = base64_decode($licenseKey, true);
            $json = $decoded === false ? null : json_decode($decoded, true);

            if (is_array($json)) {
                return $json;
            }
        }

        return [];
    }
}
