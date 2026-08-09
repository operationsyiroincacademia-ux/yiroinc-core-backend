<?php

if (!defined('ABSPATH')) {
    exit;
}

class YAC_JWT_Service {

    /**
     * Token expiry in seconds.
     */
    const EXPIRY = 86400; // 24 hours

    /**
     * Generate token.
     */
    public static function generate($payload) {

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $payload['iat'] = time();
        $payload['exp'] = time() + self::EXPIRY;

        $base64_header = self::base64_url_encode(wp_json_encode($header));
        $base64_payload = self::base64_url_encode(wp_json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            $base64_header . '.' . $base64_payload,
            wp_salt('auth'),
            true
        );

        $base64_signature = self::base64_url_encode($signature);

        return $base64_header . '.' . $base64_payload . '.' . $base64_signature;

    }

    /**
     * Verify token.
     */
    public static function verify($token) {

        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        [$base64_header, $base64_payload, $base64_signature] = $parts;

        $expected_signature = self::base64_url_encode(
            hash_hmac(
                'sha256',
                $base64_header . '.' . $base64_payload,
                wp_salt('auth'),
                true
            )
        );

        if (!hash_equals($expected_signature, $base64_signature)) {
            return false;
        }

        $payload = json_decode(self::base64_url_decode($base64_payload), true);

        if (!$payload || empty($payload['exp']) || time() > $payload['exp']) {
            return false;
        }

        return $payload;

    }

    /**
     * Base64 URL encode.
     */
    private static function base64_url_encode($data) {

        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

    }

    /**
     * Base64 URL decode.
     */
    private static function base64_url_decode($data) {

        return base64_decode(strtr($data, '-_', '+/'));

    }

}