<?php
/**
 * Injectable HTTP transport boundary.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform\Http;

if (!defined('ABSPATH')) {
    exit;
}

interface Transport {
    /**
     * @param array<string,mixed> $arguments
     * @return array<string,mixed>|\WP_Error
     */
    public function request(string $url, array $arguments);
}
