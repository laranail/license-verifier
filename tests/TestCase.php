<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;
use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\AsymmetricPublicKey;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;
use ParagonIE\Paseto\Protocol\Version4;
use Simtabi\Laranail\Licence\Verifier\Providers\LicenceVerifierServiceProvider;
use Simtabi\Laranail\Licence\Verifier\Services\FingerprintGenerator;

class TestCase extends Orchestra
{
    protected ?AsymmetricSecretKey $privateKey = null;

    protected ?AsymmetricPublicKey $publicKey = null;

    protected ?string $testStoragePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Simtabi\\Laranail\\Licence\\Verifier\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function tearDown(): void
    {
        // Clean up test storage
        if (File::isDirectory($this->testStoragePath)) {
            File::deleteDirectory($this->testStoragePath);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            LicenceVerifierServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        // Initialize test properties early
        $this->initializeTestProperties();

        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set app key for encryption
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        config()->set('license-verifier.server_url', 'https://licensing.test');
        config()->set('license-verifier.api_version', 'v1');
        config()->set('license-verifier.public_key', $this->publicKey instanceof AsymmetricPublicKey ? $this->publicKey->encode() : '');
        config()->set('license-verifier.storage_path', $this->testStoragePath);
        config()->set('license-verifier.storage.path', $this->testStoragePath);
        config()->set('license-verifier.cache.enabled', true);
        config()->set('license-verifier.cache.store', 'array');
        config()->set('license-verifier.heartbeat.enabled', false);

        // Run migrations
        $migration = include __DIR__.'/../database/migrations/create_license_verifier_table.php.stub';
        $migration->up();
    }

    protected function initializeTestProperties(): void
    {
        if (! $this->privateKey instanceof AsymmetricSecretKey) {
            // Generate test keys for PASETO v4
            $this->privateKey = AsymmetricSecretKey::generate(new Version4);
            $this->publicKey = $this->privateKey->getPublicKey();

            // Set test storage path
            $this->testStoragePath = sys_get_temp_dir().'/licensing-test-'.uniqid();
            File::makeDirectory($this->testStoragePath, 0755, true);
        }
    }

    protected function generateTestToken(array $claims = []): string
    {
        $this->initializeTestProperties();
        $builder = Builder::getPublic($this->privateKey, new Version4);

        return $builder
            ->withClaims(array_merge($this->defaultTestClaims(), $claims))
            ->toString();
    }

    /**
     * The default claim set shared by the token helpers.
     *
     * @return array<string, mixed>
     */
    protected function defaultTestClaims(): array
    {
        return [
            'sub' => '1',
            'iss' => 'laravel-licensing',
            'license_id' => 1,
            'license_key_hash' => hash('sha256', 'TEST-LICENSE-KEY'),
            'usage_fingerprint' => app(FingerprintGenerator::class)->generate(),
            'status' => 'active',
            'max_usages' => 5,
            'exp' => now()->addYear()->toIso8601String(),
            // Slightly in the past so PASETO's nbf/iat checks never race the
            // parse-time clock (deterministic across runs).
            'nbf' => now()->subMinute()->toIso8601String(),
            'iat' => now()->subMinute()->toIso8601String(),
            'force_online_after' => now()->addDays(14)->toIso8601String(),
        ];
    }

    /**
     * Generate a test token with a JSON footer (for certificate chain tests)
     *
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>  $footer
     */
    protected function generateTestTokenWithFooter(array $claims = [], array $footer = []): string
    {
        $this->initializeTestProperties();
        $builder = Builder::getPublic($this->privateKey, new Version4);

        return $builder
            ->withClaims(array_merge($this->defaultTestClaims(), $claims))
            ->setFooterArray($footer)
            ->toString();
    }

    /**
     * Generate a token actually SIGNED by a certificate chain's signing key, with the
     * chain in its footer — the realistic shape for cert-chain verification. The
     * cert-bound signing key is the one that verifies the token.
     *
     * @param  array{signing_secret_key: string, chain: array<string, mixed>}  $chainData
     * @param  array<string, mixed>  $claims
     * @param  array<string, mixed>|null  $footerChain  override the footer chain (e.g. to forge a mismatch)
     */
    protected function generateTestTokenSignedByChain(array $chainData, array $claims = [], ?array $footerChain = null): string
    {
        $this->initializeTestProperties();

        $signingKey = new \ParagonIE\Paseto\Keys\Version4\AsymmetricSecretKey($chainData['signing_secret_key']);

        return Builder::getPublic($signingKey, new Version4)
            ->withClaims(array_merge($this->defaultTestClaims(), $claims))
            ->setFooterArray(['chain' => $footerChain ?? $chainData['chain']])
            ->toString();
    }

    /**
     * Generate a test root+signing keypair and signed certificate for chain verification tests
     *
     * @return array{root_public_key: string, signing_public_key: string, signing_secret_key: string, chain: array<string, mixed>}
     */
    protected function generateTestCertificateChain(): array
    {
        $rootKeypair = sodium_crypto_sign_keypair();
        $rootPublicKey = sodium_crypto_sign_publickey($rootKeypair);
        $rootSecretKey = sodium_crypto_sign_secretkey($rootKeypair);

        $signingKeypair = sodium_crypto_sign_keypair();
        $signingPublicKey = sodium_crypto_sign_publickey($signingKeypair);
        $signingSecretKey = sodium_crypto_sign_secretkey($signingKeypair);

        $certificate = [
            'kid' => 'test-signing-key',
            'public_key' => base64_encode($signingPublicKey),
            'valid_from' => now()->subDay()->toIso8601String(),
            'valid_until' => now()->addYear()->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
            'issuer_kid' => 'test-root-key',
        ];

        $signature = sodium_crypto_sign_detached(json_encode($certificate), $rootSecretKey);

        $signedCertificate = json_encode([
            'certificate' => $certificate,
            'signature' => base64_encode($signature),
        ]);

        return [
            'root_public_key' => base64_encode($rootPublicKey),
            'signing_public_key' => base64_encode($signingPublicKey),
            'signing_secret_key' => $signingSecretKey,
            'chain' => [
                'signing' => [
                    'kid' => 'test-signing-key',
                    'public_key' => base64_encode($signingPublicKey),
                    'certificate' => $signedCertificate,
                    'valid_from' => now()->subDay()->toIso8601String(),
                    'valid_until' => now()->addYear()->toIso8601String(),
                ],
                'root' => [
                    'kid' => 'test-root-key',
                    'public_key' => base64_encode($rootPublicKey),
                    'valid_from' => now()->subYear()->toIso8601String(),
                    'valid_until' => now()->addYears(5)->toIso8601String(),
                ],
            ],
        ];
    }
}
