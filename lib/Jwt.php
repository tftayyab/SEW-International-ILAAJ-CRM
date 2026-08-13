<?php
/**
 * Compact HS256 JWT encode/decode (no Composer package).
 */

declare(strict_types=1);

final class Jwt
{
    public static function encode(array $payload, string $secret): string
    {
        $header = self::b64url(json_encode(['typ' => 'JWT', 'alg' => 'HS256'], JSON_UNESCAPED_SLASHES));
        $body = self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $sig = self::b64url(hash_hmac('sha256', $header . '.' . $body, $secret, true));
        return $header . '.' . $body . '.' . $sig;
    }

    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $body, $sig] = $parts;
        $expected = self::b64url(hash_hmac('sha256', $header . '.' . $body, $secret, true));
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $payload = json_decode(self::b64url_decode($body), true);
        if (!is_array($payload)) {
            return null;
        }
        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            return null;
        }
        if (isset($payload['nbf']) && time() < (int) $payload['nbf']) {
            return null;
        }
        return $payload;
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64url_decode(string $encoded): string
    {
        $remainder = strlen($encoded) % 4;
        if ($remainder > 0) {
            $encoded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }
}
