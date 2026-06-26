<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Drivers;

use Illuminate\Http\Client\Response;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseInfo;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseRequest;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\LicenseStatus;
use Simtabi\Laranail\Licence\Verifier\ValueObjects\VerificationResult;

/**
 * Freemius license activation. When a `secret_key` is configured each request is
 * signed with the Freemius FS-Auth scheme (Authorization: FS <scope>:<id>:<sig>
 * over METHOD\nContent-MD5\nContent-Type\nDate\nURI). Verification is install-token
 * based (offline against the local record), matching Freemius's model.
 *
 * @see https://docs.freemius.com/api/installations/activate-license
 */
final class FreemiusDriver extends AbstractHttpDriver
{
    public function activate(LicenseRequest $request): VerificationResult
    {
        $productId = (string) $this->cfg('product_id');
        $uri = "/v1/products/{$productId}/licenses/activate.json";

        $response = $this->signedRequest('POST', $uri, array_filter([
            'uid' => $this->fingerprint($request->fingerprint),
            'license_key' => $request->key,
        ]));

        $data = (array) $response->json();

        if (! $response->successful() || isset($data['error'])) {
            return VerificationResult::invalid(
                LicenseStatus::Invalid,
                message: $data['error']['message'] ?? null,
                raw: $data,
            );
        }

        $this->remember($request->key, [
            'status' => 'active',
            'expires_at' => $data['expiration'] ?? null,
            'metadata' => ['install_id' => $data['install_id'] ?? null, 'install_api_token' => $data['install_api_token'] ?? null],
        ]);

        return VerificationResult::valid(expiresAt: $data['expiration'] ?? null, raw: $data);
    }

    public function name(): string
    {
        return 'freemius';
    }

    public function verify(?string $key = null): VerificationResult
    {
        $key ??= (string) config('license-verifier.license_key');
        $record = (array) $this->store()->get($key);

        if (($record['status'] ?? null) !== 'active') {
            return VerificationResult::invalid(LicenseStatus::Unactivated, raw: $record);
        }

        return VerificationResult::valid(expiresAt: $record['expires_at'] ?? null, raw: $record);
    }

    public function deactivate(?string $key = null, ?string $reason = null): bool
    {
        $key ??= (string) config('license-verifier.license_key');
        $record = (array) $this->store()->get($key);
        $installId = $record['metadata']['install_id'] ?? null;
        $productId = (string) $this->cfg('product_id');

        if ($installId !== null) {
            rescue(fn (): Response => $this->signedRequest('DELETE', "/v1/products/{$productId}/installs/{$installId}.json"), report: false);
        }

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
        return $this->http()->get('/v1/ping.json')->status() !== 0;
    }

    /**
     * Send a request with the body digest matched byte-for-byte and FS-Auth signed
     * (when a secret key is configured).
     *
     * @param  array<string, mixed>  $params
     */
    private function signedRequest(string $method, string $uri, array $params = []): Response
    {
        $body = $params === [] ? '' : (string) json_encode($params);

        $request = $this->http($this->fsAuthHeaders($method, $uri, $body));

        if ($body !== '') {
            $request = $request->withBody($body, 'application/json');
        }

        return $request->send($method, $uri);
    }

    /**
     * @return array<string, string>
     */
    private function fsAuthHeaders(string $method, string $uri, string $body): array
    {
        $secret = (string) $this->cfg('secret_key');

        if ($secret === '') {
            return [];
        }

        $contentMd5 = $body === '' ? '' : md5($body);
        $contentType = $body === '' ? '' : 'application/json';
        $date = $this->httpDate();

        $stringToSign = $method."\n".$contentMd5."\n".$contentType."\n".$date."\n".$uri;
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $stringToSign, $secret, true)), '+/', '-_'), '=');

        $headers = [
            'Date' => $date,
            'Authorization' => 'FS product:'.($this->cfg('product_id')).':'.$signature,
        ];

        if ($contentMd5 !== '') {
            $headers['Content-MD5'] = $contentMd5;
        }

        return $headers;
    }
}
