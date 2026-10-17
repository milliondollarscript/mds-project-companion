<?php
/**
 * Bounded WordPress HTTP transport.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform\Http;

if (!defined('ABSPATH')) {
    exit;
}

final class WordPressTransport implements Transport {
    public function __construct(private bool $local_development = false) {
    }

    /**
     * @param array<string,mixed> $arguments
     * @return array<string,mixed>|\WP_Error
     */
    public function request(string $url, array $arguments) {
        if (!EndpointPolicy::validate($url, $this->local_development)) {
            return new \WP_Error('unsafe_endpoint', __('The hosted API endpoint did not pass outbound request safety checks.', 'mds-project-companion'));
        }
        $arguments = array_merge($arguments, [
            'timeout' => EndpointPolicy::TIMEOUT_SECONDS,
            'redirection' => 0,
            'sslverify' => true,
            'reject_unsafe_urls' => !$this->local_development,
            'limit_response_size' => EndpointPolicy::MAX_RESPONSE_BYTES + 1,
        ]);
        return $this->local_development
            ? wp_remote_request($url, $arguments)
            : wp_safe_remote_request($url, $arguments);
    }
}
