<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Support;

use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Licence\Verifier\Contracts\IpResolver;
use Throwable;

/**
 * Resolves the server IP from a configured static IP, otherwise via a
 * third-party lookup service (ported from Botble's Helper::getIpFromThirdParty).
 */
final class ThirdPartyIpResolver implements IpResolver
{
    public function resolve(): string
    {
        $static = config('license-verifier.ip.static_ip');

        if ($static && filter_var($static, FILTER_VALIDATE_IP)) {
            return (string) $static;
        }

        try {
            $url = (string) config('license-verifier.ip.lookup_url', 'https://ipecho.net/plain');
            $timeout = (int) config('license-verifier.ip.lookup_timeout', 5);
            $ip = trim((string) Http::timeout($timeout)->get($url)->body());

            return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
        } catch (Throwable) {
            return '127.0.0.1';
        }
    }
}
