<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Drivers\CryptolensDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\FreemiusDriver;
use Simtabi\Laranail\Licence\Verifier\Drivers\LicenseSpringDriver;
use Simtabi\Laranail\Licence\Verifier\Support\RsaPublicKey;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;

/**
 * @return array{priv: string, pub: string, xml: string}
 */
function rsaTestKeys(): array
{
    $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($res, $priv);
    $details = openssl_pkey_get_details($res);

    return [
        'priv' => $priv,
        'pub' => $details['key'],
        'xml' => '<RSAKeyValue><Modulus>'.base64_encode((string) $details['rsa']['n'])
            .'</Modulus><Exponent>'.base64_encode((string) $details['rsa']['e']).'</Exponent></RSAKeyValue>',
    ];
}

function cryptolensSignedResponse(string $priv): array
{
    $licenseJson = json_encode(['Expires' => '2030-01-01', 'F1' => true]);
    openssl_sign($licenseJson, $sig, $priv, OPENSSL_ALGO_SHA256);

    return ['result' => 0, 'licenseKey' => base64_encode($licenseJson), 'signature' => base64_encode((string) $sig)];
}

it('accepts a valid Cryptolens RSA signature (PEM key)', function (): void {
    $keys = rsaTestKeys();
    Http::fake(['api.cryptolens.io/*' => Http::response(cryptolensSignedResponse($keys['priv']))]);

    $result = new CryptolensDriver(['token' => 't', 'product_id' => '1', 'public_key' => $keys['pub'], 'base_url' => 'https://api.cryptolens.io'])
        ->activate(new LicenseRequest('CL'));

    expect($result->valid)->toBeTrue()->and($result->expiresAt)->toBe('2030-01-01');
});

it('accepts a valid Cryptolens signature via an XML public key', function (): void {
    $keys = rsaTestKeys();
    expect(RsaPublicKey::toPem($keys['xml']))->toContain('-----BEGIN PUBLIC KEY-----');

    Http::fake(['api.cryptolens.io/*' => Http::response(cryptolensSignedResponse($keys['priv']))]);

    $result = new CryptolensDriver(['token' => 't', 'product_id' => '1', 'public_key' => $keys['xml'], 'base_url' => 'https://api.cryptolens.io'])
        ->activate(new LicenseRequest('CL'));

    expect($result->valid)->toBeTrue();
});

it('rejects a tampered Cryptolens signature (fail closed)', function (): void {
    $keys = rsaTestKeys();
    $response = cryptolensSignedResponse($keys['priv']);
    $response['signature'] = base64_encode('forged-signature');

    Http::fake(['api.cryptolens.io/*' => Http::response($response)]);

    $result = new CryptolensDriver(['token' => 't', 'product_id' => '1', 'public_key' => $keys['pub'], 'base_url' => 'https://api.cryptolens.io'])
        ->activate(new LicenseRequest('CL'));

    expect($result->valid)->toBeFalse()
        ->and($result->message)->toContain('signature verification failed');
});

it('skips Cryptolens verification when no public key is configured', function (): void {
    Http::fake(['api.cryptolens.io/*' => Http::response(['result' => 0, 'licenseKey' => ['Expires' => '2031-01-01']])]);

    $result = new CryptolensDriver(['token' => 't', 'product_id' => '1', 'base_url' => 'https://api.cryptolens.io'])
        ->activate(new LicenseRequest('CL'));

    expect($result->valid)->toBeTrue()->and($result->expiresAt)->toBe('2031-01-01');
});

it('signs LicenseSpring requests with the HMAC Date signature', function (): void {
    Http::fake(['api.licensespring.com/*' => Http::response(['license_active' => true, 'license_enabled' => true])]);

    new LicenseSpringDriver(['api_key' => 'k', 'shared_key' => 'secret', 'product' => 'p', 'base_url' => 'https://api.licensespring.com'])
        ->activate(new LicenseRequest('LS', fingerprint: 'fp'));

    Http::assertSent(function ($request): bool {
        $date = $request->header('Date')[0] ?? '';
        $expected = base64_encode(hash_hmac('sha256', "date: {$date}", 'secret', true));

        return $request->hasHeader('Api-Key', 'k')
            && $date !== ''
            && str_contains($request->header('Authorization')[0] ?? '', 'signature="'.$expected.'"');
    });
});

it('signs Freemius requests with FS-Auth (Authorization + Content-MD5 + Date)', function (): void {
    Http::fake(['api.freemius.com/*' => Http::response(['install_id' => 1, 'expiration' => '2030-01-01'])]);

    new FreemiusDriver(['product_id' => '123', 'secret_key' => 'sk', 'base_url' => 'https://api.freemius.com'])
        ->activate(new LicenseRequest('FM', fingerprint: 'uid'));

    Http::assertSent(fn ($request): bool => str_starts_with($request->header('Authorization')[0] ?? '', 'FS product:123:')
        && $request->hasHeader('Content-MD5')
        && $request->hasHeader('Date'));
});
