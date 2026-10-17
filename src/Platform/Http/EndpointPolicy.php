<?php
/**
 * Future development-endpoint validation without defining hosted routes.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform\Http;

if (!defined('ABSPATH')) {
    exit;
}

final class EndpointPolicy {
    public const TIMEOUT_SECONDS = 10;
    public const MAX_RESPONSE_BYTES = 1048576;
    public const MAX_RETRIES = 2;

    public static function validate(string $url, bool $local_development = false): bool {
        if (!self::validate_syntax($url, $local_development)) {
            return false;
        }
        $parts = wp_parse_url(trim($url));
        $host = strtolower((string) $parts['host']);
        if (self::is_loopback($host)) {
            return $local_development;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if (!is_array($ips) || !$ips) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }

    public static function validate_syntax(string $url, bool $local_development = false): bool {
        $parts = wp_parse_url(trim($url));
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if (!empty($parts['user']) || !empty($parts['pass']) || !empty($parts['query']) || !empty($parts['fragment'])) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        $scheme = strtolower((string) $parts['scheme']);
        if ('https' !== $scheme && !($local_development && 'http' === $scheme && self::is_loopback($host))) {
            return false;
        }
        if (self::is_loopback($host)) {
            return $local_development;
        }
        if ('localhost' === $host || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return false;
        }
        if (
            filter_var($host, FILTER_VALIDATE_IP)
            && !filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
        ) {
            return false;
        }
        return true;
    }

    private static function is_loopback(string $host): bool {
        return in_array(trim($host, '[]'), ['127.0.0.1', '::1'], true);
    }
}
