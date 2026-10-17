<?php
/**
 * Audit metadata redaction.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Audit;

if (!defined('ABSPATH')) {
    exit;
}
final class Redactor {
    private const SECRET_PATTERN = '/(?:authorization|cookie|password|passwd|token|secret|api[_-]?key|webhook|credential)/i';
    private const MAX_DEPTH = 5;
    private const MAX_ITEMS = 50;
    private const MAX_STRING = 300;

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function clean($value, int $depth = 0) {
        if ($depth >= self::MAX_DEPTH) {
            return '[bounded]';
        }
        if (is_array($value)) {
            $clean = [];
            foreach (array_slice($value, 0, self::MAX_ITEMS, true) as $key => $item) {
                $safe_key = sanitize_key((string) $key);
                if (preg_match(self::SECRET_PATTERN, (string) $key)) {
                    $clean[$safe_key ?: 'redacted'] = '[redacted]';
                    continue;
                }
                $clean[$safe_key ?: 'value'] = self::clean($item, $depth + 1);
            }
            return $clean;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || null === $value) {
            return $value;
        }

        $string = wp_strip_all_tags((string) $value);
        if (preg_match('/\b(?:bearer\s+|sk-[a-z0-9]|https?:\/\/[^@\s]+:[^@\s]+@)/i', $string)) {
            return '[redacted]';
        }
        return mb_substr($string, 0, self::MAX_STRING);
    }
}
