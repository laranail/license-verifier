<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Verifier\Support;

/**
 * Normalizes an RSA public key to PEM. Accepts a PEM string as-is, or converts a
 * .NET / Cryptolens XML `<RSAKeyValue><Modulus/><Exponent/></RSAKeyValue>` form
 * to a PEM `PUBLIC KEY` (SubjectPublicKeyInfo) via a minimal ASN.1/DER encoder —
 * no external dependency.
 */
final class RsaPublicKey
{
    public static function toPem(?string $key): ?string
    {
        $key = trim((string) $key);

        if ($key === '') {
            return null;
        }

        if (str_contains($key, '-----BEGIN')) {
            return $key; // already PEM
        }

        if (preg_match('#<Modulus>(.+?)</Modulus>#s', $key, $m) !== 1
            || preg_match('#<Exponent>(.+?)</Exponent>#s', $key, $e) !== 1) {
            return null;
        }

        $modulus = base64_decode(trim($m[1]), true);
        $exponent = base64_decode(trim($e[1]), true);

        if ($modulus === false || $exponent === false || $modulus === '' || $exponent === '') {
            return null;
        }

        $rsaPublicKey = self::seq(self::int($modulus).self::int($exponent));
        // AlgorithmIdentifier: SEQUENCE { OID 1.2.840.113549.1.1.1 (rsaEncryption), NULL }
        $algorithm = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $spki = self::seq($algorithm.self::bitString($rsaPublicKey));

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n").'-----END PUBLIC KEY-----';
    }

    private static function len(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xFF).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private static function int(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '') {
            $bytes = "\x00";
        }

        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00".$bytes; // keep it positive
        }

        return "\x02".self::len(strlen($bytes)).$bytes;
    }

    private static function seq(string $content): string
    {
        return "\x30".self::len(strlen($content)).$content;
    }

    private static function bitString(string $content): string
    {
        $content = "\x00".$content; // zero unused bits

        return "\x03".self::len(strlen($content)).$content;
    }
}
