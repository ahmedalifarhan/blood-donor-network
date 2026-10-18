<?php

namespace App\Support;

/**
 * Masks personal data before it reaches logs (constitution II, rule AU-05).
 */
final class Masking
{
    public const MAX_JSON_LENGTH = 5000;

    /** Any run of 9+ digits keeps its first 4 and last 4: 201012345678 → 2010****5678. */
    public static function text(string $value): string
    {
        return preg_replace_callback(
            '/\d{9,}/',
            static fn (array $m): string => substr($m[0], 0, 4).'****'.substr($m[0], -4),
            $value,
        ) ?? $value;
    }

    /**
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    public static function context(array $context): array
    {
        $masked = [];

        foreach ($context as $key => $value) {
            $masked[$key] = match (true) {
                is_string($value) => self::text($value),
                is_array($value) => self::context($value),
                default => $value,
            };
        }

        return $masked;
    }

    /** IPv4 → /24 network, IPv6 → /48 network, anything else → empty string. */
    public static function ip(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $octets = explode('.', $ip);

            return "{$octets[0]}.{$octets[1]}.{$octets[2]}.0/24";
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $binary = (string) inet_pton($ip);
            $network = inet_ntop(substr($binary, 0, 6).str_repeat("\0", 10));

            return $network === false ? '' : $network.'/48';
        }

        return '';
    }

    /**
     * Masked context as compact JSON, at most MAX_JSON_LENGTH characters.
     *
     * @param  array<array-key, mixed>  $context
     */
    public static function json(array $context): string
    {
        $json = json_encode(
            self::context($context),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        if (mb_strlen($json) > self::MAX_JSON_LENGTH) {
            return mb_substr($json, 0, self::MAX_JSON_LENGTH - 1).'…';
        }

        return $json;
    }
}
