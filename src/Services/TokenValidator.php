<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Services;

use Carbon\Carbon;
use Exception;
use ParagonIE\Paseto\Keys\Base\AsymmetricPublicKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Protocol\Version4;
use ParagonIE\Paseto\ProtocolCollection;
use ParagonIE\Paseto\Rules\IssuedBy;
use Simtabi\Laranail\Licence\Verifier\Exceptions\LicensingException;
use Throwable;

class TokenValidator
{
    protected ?AsymmetricPublicKey $publicKey = null;

    protected ?string $rootPublicKey = null;

    protected Parser $parser;

    public function __construct(
        protected FingerprintGenerator $fingerprintGenerator
    ) {
        $this->initializeParser();
    }

    /**
     * Validate a PASETO token and return its claims
     */
    public function validate(string $token): array
    {
        if (! $this->publicKey instanceof AsymmetricPublicKey) {
            throw LicensingException::publicKeyMissing();
        }

        try {
            $parsedToken = $this->parser->parse($token);
            $claims = $parsedToken->getClaims();

            $footer = $this->extractFooter($token);

            if (! empty($footer['chain']) && ! $this->verifyCertificateChain($footer)) {
                throw LicensingException::invalidCertificateChain();
            }

            if (! $this->validateFingerprint($claims)) {
                throw LicensingException::fingerprintMismatch();
            }

            if (! $this->validateExpiration($claims)) {
                throw LicensingException::licenseExpired();
            }

            if (! $this->validateNotBefore($claims)) {
                throw LicensingException::tokenNotYetValid();
            }

            if (! $this->validateStatus($claims)) {
                throw LicensingException::invalidLicenseStatus($claims['status'] ?? 'unknown');
            }

            if (! $this->validateUsageLimits($claims)) {
                throw LicensingException::usageLimitExceeded();
            }

            return $claims;
        } catch (Exception $e) {
            if ($e instanceof LicensingException) {
                throw $e;
            }

            if (str_contains($e->getMessage(), 'was not issued by')) {
                throw LicensingException::invalidIssuer();
            }

            if (str_contains($e->getMessage(), 'expired') || str_contains($e->getMessage(), 'token has expired')) {
                throw LicensingException::licenseExpired();
            }

            throw LicensingException::invalidToken();
        }
    }

    /**
     * Check if a token is valid without throwing exceptions
     */
    public function isValid(string $token): bool
    {
        try {
            $this->validate($token);

            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Check if the token requires an online refresh (force_online_after exceeded)
     */
    public function requiresOnlineRefresh(string $token): bool
    {
        try {
            $parsedToken = $this->parser->parse($token);
            $claims = $parsedToken->getClaims();

            if (! isset($claims['force_online_after'])) {
                return false;
            }

            return Carbon::parse($claims['force_online_after'])->isPast();
        } catch (Exception) {
            return true;
        }
    }

    /**
     * Get token expiration time
     */
    public function getExpiration(string $token): ?Carbon
    {
        try {
            $claims = $this->validate($token);

            if (! isset($claims['exp'])) {
                return null;
            }

            return Carbon::parse($claims['exp']);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Check if token is expiring soon
     */
    public function isExpiringSoon(string $token, int $daysThreshold = 7): bool
    {
        $expiration = $this->getExpiration($token);

        if (! $expiration instanceof Carbon) {
            return false;
        }

        $now = now();
        $daysUntilExpiration = $now->diffInDays($expiration, false);

        return $daysUntilExpiration > 0 && $daysUntilExpiration <= $daysThreshold;
    }

    /**
     * Extract license information from token claims
     */
    public function extractLicenseInfo(string $token): array
    {
        try {
            $claims = $this->validate($token);

            return [
                'license_id' => $claims['license_id'] ?? null,
                'license_key_hash' => $claims['license_key_hash'] ?? null,
                'status' => $claims['status'] ?? null,
                'max_usages' => $claims['max_usages'] ?? null,
                'expires_at' => $claims['exp'] ?? null,
                'issued_at' => $claims['iat'] ?? null,
                'not_before' => $claims['nbf'] ?? null,
                'issuer' => $claims['iss'] ?? null,
                'license_expires_at' => $claims['license_expires_at'] ?? null,
                'force_online_after' => $claims['force_online_after'] ?? null,
                'grace_until' => $claims['grace_until'] ?? null,
                'usage_fingerprint' => $claims['usage_fingerprint'] ?? null,
                'licensable_type' => $claims['licensable_type'] ?? null,
                'licensable_id' => $claims['licensable_id'] ?? null,
                'entitlements' => $claims['entitlements'] ?? null,
            ];
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Initialize the PASETO parser
     */
    protected function initializeParser(): void
    {
        $publicKeyString = config('license-verifier.public_key');

        if (! $publicKeyString) {
            return;
        }

        try {
            $this->publicKey = AsymmetricPublicKey::fromEncodedString($publicKeyString, new Version4);
            $this->parser = Parser::getPublic($this->publicKey, ProtocolCollection::v4());
            $this->configureParser();
        } catch (Exception) {
            throw LicensingException::invalidConfiguration('Invalid public key format');
        }
    }

    /**
     * Update the public key used for token validation (for key rotation)
     */
    public function updatePublicKey(string $publicKeyString): void
    {
        try {
            $this->publicKey = AsymmetricPublicKey::fromEncodedString($publicKeyString, new Version4);
            $this->parser = Parser::getPublic($this->publicKey, ProtocolCollection::v4());
            $this->configureParser();
        } catch (Exception) {
            throw LicensingException::invalidConfiguration('Invalid public key format');
        }
    }

    /**
     * Configure parser rules (issuer validation, etc.)
     */
    protected function configureParser(): void
    {
        // Disable built-in NotExpired rule - we handle expiration manually with clock skew
        $this->parser->setNonExpiring(true);

        $issuer = config('license-verifier.issuer');

        if ($issuer) {
            $this->parser->addRule(new IssuedBy($issuer));
        }
    }

    /**
     * Set the root public key for certificate chain verification
     */
    public function setRootPublicKey(string $rootPublicKey): void
    {
        $this->rootPublicKey = $rootPublicKey;
    }

    /**
     * Extract and decode the JSON footer from a PASETO token
     *
     * @return array<string, mixed>
     */
    protected function extractFooter(string $token): array
    {
        try {
            $footer = Parser::extractFooter($token);

            if ($footer === '') {
                return [];
            }

            $decoded = json_decode($footer, true);

            return is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Verify the certificate chain from the token footer against the stored root key
     *
     * @param  array<string, mixed>  $footer
     */
    protected function verifyCertificateChain(array $footer): bool
    {
        if (! isset($footer['chain'])) {
            return false;
        }

        $chain = $footer['chain'];

        if (! $this->rootPublicKey) {
            return false;
        }

        $chainRootKey = $chain['root']['public_key'] ?? null;

        if (! $chainRootKey || ! hash_equals($this->rootPublicKey, $chainRootKey)) {
            return false;
        }

        $signingCertificate = $chain['signing']['certificate'] ?? null;

        if (! $signingCertificate) {
            return false;
        }

        return $this->verifyCertificateSignature($signingCertificate, $this->rootPublicKey);
    }

    /**
     * Verify a certificate's Ed25519 detached signature using the root public key
     */
    protected function verifyCertificateSignature(string $certificateJson, string $rootPublicKeyBase64): bool
    {
        try {
            $data = json_decode($certificateJson, true);

            if (! isset($data['certificate'], $data['signature'])) {
                return false;
            }

            $certificatePayload = json_encode($data['certificate']);
            $signature = base64_decode((string) $data['signature']);
            $publicKey = base64_decode($rootPublicKeyBase64);

            return sodium_crypto_sign_verify_detached($signature, $certificatePayload, $publicKey);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Validate fingerprint claim matches current device
     */
    protected function validateFingerprint(array $claims): bool
    {
        if (! isset($claims['usage_fingerprint'])) {
            return false;
        }

        $currentFingerprint = $this->fingerprintGenerator->generate();

        return hash_equals($claims['usage_fingerprint'], $currentFingerprint);
    }

    /**
     * Validate token expiration
     */
    protected function validateExpiration(array $claims): bool
    {
        if (! isset($claims['exp'])) {
            return true;
        }

        $clockSkew = (int) config('license-verifier.clock_skew_seconds', 60);

        return Carbon::parse($claims['exp'])->addSeconds($clockSkew)->isFuture();
    }

    /**
     * Validate not-before claim with clock skew tolerance
     */
    protected function validateNotBefore(array $claims): bool
    {
        if (! isset($claims['nbf'])) {
            return true;
        }

        $clockSkew = (int) config('license-verifier.clock_skew_seconds', 60);

        return Carbon::parse($claims['nbf'])->subSeconds($clockSkew)->isPast();
    }

    /**
     * Validate license status is usable (active or grace)
     */
    protected function validateStatus(array $claims): bool
    {
        if (! isset($claims['status'])) {
            return true;
        }

        return in_array($claims['status'], ['active', 'grace'], true);
    }

    /**
     * Validate usage limits
     */
    protected function validateUsageLimits(array $claims): bool
    {
        // Seat/usage limits are enforced server-side at activation/heartbeat; the
        // offline client cannot know the current usage count, so this never fails
        // here. (Kept as a hook for drivers that embed a usable seat count.)
        return true;
    }
}
