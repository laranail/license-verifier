<?php

declare(strict_types=1);

use Carbon\Carbon;
use Simtabi\Laranail\Licence\Verifier\Exceptions\LicensingException;
use Simtabi\Laranail\Licence\Verifier\Services\FingerprintGenerator;
use Simtabi\Laranail\Licence\Verifier\Services\TokenValidator;

beforeEach(function (): void {
    $this->fingerprintGenerator = new FingerprintGenerator;
    $this->validator = new TokenValidator($this->fingerprintGenerator);
});

it('validates a valid token', function (): void {
    $token = $this->generateTestToken();

    $claims = $this->validator->validate($token);

    expect($claims)->toBeArray();
    expect($claims)->toHaveKey('license_id');
    expect($claims['status'])->toBe('active');
});

it('throws exception for invalid token', function (): void {
    $this->validator->validate('invalid-token');
})->throws(LicensingException::class);

it('throws exception for expired token', function (): void {
    $token = $this->generateTestToken([
        'exp' => now()->subDay()->toIso8601String(),
    ]);

    $this->validator->validate($token);
})->throws(LicensingException::class, 'The license has expired.');

it('throws exception for fingerprint mismatch', function (): void {
    $token = $this->generateTestToken([
        'usage_fingerprint' => 'wrong-fingerprint',
    ]);

    $this->validator->validate($token);
})->throws(LicensingException::class, 'Device fingerprint does not match the licensed device.');

it('throws exception for invalid license status', function (): void {
    $token = $this->generateTestToken([
        'status' => 'suspended',
    ]);

    $this->validator->validate($token);
})->throws(LicensingException::class, "License status 'suspended' is not valid for use.");

it('allows grace status', function (): void {
    $token = $this->generateTestToken([
        'status' => 'grace',
        'grace_until' => now()->addDays(7)->toIso8601String(),
    ]);

    $claims = $this->validator->validate($token);

    expect($claims['status'])->toBe('grace');
});

it('allows unlimited usage when max_usages is -1', function (): void {
    $token = $this->generateTestToken([
        'max_usages' => -1,
    ]);

    $claims = $this->validator->validate($token);

    expect($claims)->toBeArray();
});

it('checks if token is valid without throwing exceptions', function (): void {
    $validToken = $this->generateTestToken();
    $invalidToken = 'invalid-token';

    expect($this->validator->isValid($validToken))->toBeTrue();
    expect($this->validator->isValid($invalidToken))->toBeFalse();
});

it('gets token expiration date', function (): void {
    $expirationDate = now()->addMonth();
    $token = $this->generateTestToken([
        'exp' => $expirationDate->toIso8601String(),
    ]);

    $expiration = $this->validator->getExpiration($token);

    expect($expiration)->toBeInstanceOf(Carbon::class);
    expect($expiration->toIso8601String())->toBe($expirationDate->toIso8601String());
});

it('returns null for invalid token expiration', function (): void {
    expect($this->validator->getExpiration('invalid-token'))->toBeNull();
});

it('checks if token is expiring soon', function (): void {
    $tokenExpiringSoon = $this->generateTestToken([
        'exp' => now()->addDays(5)->toIso8601String(),
    ]);

    $tokenNotExpiringSoon = $this->generateTestToken([
        'exp' => now()->addDays(30)->toIso8601String(),
    ]);

    expect($this->validator->isExpiringSoon($tokenExpiringSoon, 7))->toBeTrue();
    expect($this->validator->isExpiringSoon($tokenNotExpiringSoon, 7))->toBeFalse();
});

it('extracts license information from token', function (): void {
    $token = $this->generateTestToken([
        'license_id' => 42,
        'status' => 'active',
        'max_usages' => 10,
        'license_expires_at' => '2027-12-31T23:59:59+00:00',
        'force_online_after' => now()->addDays(14)->toIso8601String(),
    ]);

    $info = $this->validator->extractLicenseInfo($token);

    expect($info)->toMatchArray([
        'license_id' => 42,
        'status' => 'active',
        'max_usages' => 10,
        'license_expires_at' => '2027-12-31T23:59:59+00:00',
    ]);
});

it('returns empty array for invalid token when extracting info', function (): void {
    $info = $this->validator->extractLicenseInfo('invalid-token');

    expect($info)->toBe([]);
});

it('detects when online refresh is required', function (): void {
    $token = $this->generateTestToken([
        'force_online_after' => now()->subDay()->toIso8601String(),
    ]);

    expect($this->validator->requiresOnlineRefresh($token))->toBeTrue();
});

it('detects when online refresh is not required', function (): void {
    $token = $this->generateTestToken([
        'force_online_after' => now()->addDays(14)->toIso8601String(),
    ]);

    expect($this->validator->requiresOnlineRefresh($token))->toBeFalse();
});

it('can update public key for key rotation', function (): void {
    $newKey = $this->publicKey->encode();
    $this->validator->updatePublicKey($newKey);

    $token = $this->generateTestToken();
    $claims = $this->validator->validate($token);

    expect($claims)->toBeArray();
    expect($claims['status'])->toBe('active');
});

it('throws exception for invalid public key in updatePublicKey', function (): void {
    $this->validator->updatePublicKey('invalid-key');
})->throws(LicensingException::class, 'Invalid public key format');

it('throws exception when public key is missing', function (): void {
    config(['license-verifier.public_key' => null]);

    $validator = new TokenValidator($this->fingerprintGenerator);
    $token = $this->generateTestToken();

    $validator->validate($token);
})->throws(LicensingException::class, 'Public key for token verification is not configured.');

it('throws exception for wrong issuer', function (): void {
    config(['license-verifier.issuer' => 'wrong-issuer']);

    $validator = new TokenValidator($this->fingerprintGenerator);
    $token = $this->generateTestToken(['iss' => 'laravel-licensing']);

    $validator->validate($token);
})->throws(LicensingException::class, 'Token issuer does not match expected issuer.');

it('accepts token within clock skew tolerance', function (): void {
    config(['license-verifier.clock_skew_seconds' => 60]);

    $token = $this->generateTestToken([
        'exp' => now()->subSeconds(30)->toIso8601String(),
    ]);

    $claims = $this->validator->validate($token);

    expect($claims)->toBeArray();
});

it('rejects token beyond clock skew tolerance', function (): void {
    config(['license-verifier.clock_skew_seconds' => 60]);

    $token = $this->generateTestToken([
        'exp' => now()->subSeconds(120)->toIso8601String(),
    ]);

    $this->validator->validate($token);
})->throws(LicensingException::class, 'The license has expired.');

it('accepts token with nbf slightly in the future within clock skew', function (): void {
    config(['license-verifier.clock_skew_seconds' => 60]);

    $token = $this->generateTestToken([
        'nbf' => now()->addSeconds(30)->toIso8601String(),
    ]);

    $claims = $this->validator->validate($token);

    expect($claims)->toBeArray();
});

it('rejects token with nbf far in the future', function (): void {
    config(['license-verifier.clock_skew_seconds' => 60]);

    $token = $this->generateTestToken([
        'nbf' => now()->addSeconds(120)->toIso8601String(),
    ]);

    $this->validator->validate($token);
})->throws(LicensingException::class, 'The license token is not yet valid.');

it('validates a valid certificate chain', function (): void {
    $chainData = $this->generateTestCertificateChain();

    $this->validator->setRootPublicKey($chainData['root_public_key']);

    $token = $this->generateTestTokenWithFooter([], [
        'kid' => 'test-signing-key',
        'chain' => $chainData['chain'],
    ]);

    $claims = $this->validator->validate($token);

    expect($claims)->toBeArray();
    expect($claims['status'])->toBe('active');
});

it('rejects token with invalid certificate signature in chain', function (): void {
    $chainData = $this->generateTestCertificateChain();

    $this->validator->setRootPublicKey($chainData['root_public_key']);

    $chain = $chainData['chain'];
    $tamperedCert = json_decode((string) $chain['signing']['certificate'], true);
    $tamperedCert['signature'] = base64_encode(random_bytes(64));
    $chain['signing']['certificate'] = json_encode($tamperedCert);

    $token = $this->generateTestTokenWithFooter([], [
        'kid' => 'test-signing-key',
        'chain' => $chain,
    ]);

    $this->validator->validate($token);
})->throws(LicensingException::class, 'Certificate chain verification failed.');

it('rejects token with wrong root key in chain', function (): void {
    $chainData = $this->generateTestCertificateChain();

    $wrongRootKey = base64_encode(sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()));
    $this->validator->setRootPublicKey($wrongRootKey);

    $token = $this->generateTestTokenWithFooter([], [
        'kid' => 'test-signing-key',
        'chain' => $chainData['chain'],
    ]);

    $this->validator->validate($token);
})->throws(LicensingException::class, 'Certificate chain verification failed.');

it('accepts token without footer for backward compatibility', function (): void {
    $token = $this->generateTestToken();

    $claims = $this->validator->validate($token);

    expect($claims)->toBeArray();
    expect($claims['status'])->toBe('active');
});

it('extracts license info with new fields', function (): void {
    $token = $this->generateTestToken([
        'license_id' => 42,
        'status' => 'active',
        'max_usages' => 10,
        'licensable_type' => 'App\\Models\\User',
        'licensable_id' => 99,
        'entitlements' => ['feature_a', 'feature_b'],
    ]);

    $info = $this->validator->extractLicenseInfo($token);

    expect($info)->toHaveKeys(['not_before', 'issuer', 'licensable_type', 'licensable_id', 'entitlements']);
    expect($info['licensable_type'])->toBe('App\\Models\\User');
    expect($info['licensable_id'])->toBe(99);
    expect($info['entitlements'])->toBe(['feature_a', 'feature_b']);
    expect($info['issuer'])->toBe('laravel-licensing');
});
