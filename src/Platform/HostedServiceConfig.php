<?php
/**
 * Neutral hosted-service display configuration.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform;

use MillionDollarScript\Extensions\ProjectCompanion\Platform\Http\EndpointPolicy;

if (!defined('ABSPATH')) {
    exit;
}

final class HostedServiceConfig {
    /**
     * @return array{display_name:string,api_origin:string,app_url:string,docs_url:string,local_development:bool,valid:bool}
     */
    public static function get(): array {
        $config = apply_filters('million-dollar-script/project-companion/hosted-service-config', [
            'display_name' => __('Hosted platform', 'mds-project-companion'),
            'api_origin' => '',
            'app_url' => '',
            'docs_url' => '',
            'local_development' => false,
        ]);
        $config = is_array($config) ? $config : [];
        $local_development = !empty($config['local_development'])
            && defined('WP_DEBUG')
            && WP_DEBUG;
        $api_origin = self::origin((string) ($config['api_origin'] ?? ''), $local_development);
        return [
            'display_name' => sanitize_text_field((string) ($config['display_name'] ?? __('Hosted platform', 'mds-project-companion'))),
            'api_origin' => $api_origin,
            'app_url' => self::display_url((string) ($config['app_url'] ?? ''), $local_development),
            'docs_url' => self::display_url((string) ($config['docs_url'] ?? ''), $local_development),
            'local_development' => $local_development,
            'valid' => '' !== $api_origin,
        ];
    }

    private static function origin(string $url, bool $local_development): string {
        $url = untrailingslashit(trim($url));
        if ('' === $url || !EndpointPolicy::validate_syntax($url, $local_development)) {
            return '';
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts) || !empty($parts['path']) && '/' !== $parts['path']) {
            return '';
        }
        return $url;
    }

    private static function display_url(string $url, bool $local_development): string {
        $protocols = $local_development ? ['https', 'http'] : ['https'];
        return esc_url_raw($url, $protocols);
    }
}
