<?php
/**
 * Normalized public Million Dollar Script capability snapshot.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Discovery;

use MillionDollarScript\Core\Runtime;
use MillionDollarScript\Extensions\Registry;
use MillionDollarScript\Extensions\ProjectCompanion\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Snapshot {
    public const SCHEMA_VERSION = 1;

    /**
     * @return array<string,mixed>
     */
    public static function build(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $cache = get_option(Settings::DISCOVERY_CACHE, []);
        $cache = is_array($cache) ? $cache : [];
        $available = self::available_map($cache);
        $registered = Registry::registered();
        $extensions = [];

        foreach (get_plugins() as $file => $headers) {
            if ('million-dollar-script' === dirname($file) || '.' === dirname($file)) {
                continue;
            }
            $slug = sanitize_key(dirname($file));
            if ('' === $slug || 0 !== strpos($slug, 'mds-')) {
                continue;
            }
            $custom = function_exists('get_file_data')
                ? get_file_data(WP_PLUGIN_DIR . '/' . $file, [
                    'provides' => 'MDS Provides',
                    'requires' => 'MDS Requires',
                    'recommends' => 'MDS Recommends',
                    'conflicts' => 'MDS Conflicts',
                    'service' => 'MDS Requires Service',
                    'security' => 'MDS Minimum Security Level',
                    'llm' => 'MDS LLM Safe Actions',
                    'api_manifest' => 'MDS API Manifest',
                ])
                : [];
            $remote = $available[$slug] ?? [];
            $extensions[$slug] = [
                'slug' => $slug,
                'name' => sanitize_text_field((string) ($headers['Name'] ?? $slug)),
                'version' => sanitize_text_field((string) ($headers['Version'] ?? '')),
                'installed' => true,
                'active' => function_exists('is_plugin_active') && is_plugin_active($file),
                'available' => array_key_exists($slug, $available) ? true : 'unknown',
                'bundled' => !empty($remote['bundled']),
                'commercial' => !empty($remote['license_required']) ? 'paid' : 'unknown',
                'requires_service' => self::yes((string) ($custom['service'] ?? ($remote['requires_service'] ?? ''))),
                'provides' => self::words($custom['provides'] ?? ($remote['provides'] ?? [])),
                'requires' => self::words($custom['requires'] ?? ($remote['requires'] ?? [])),
                'recommends' => self::words($custom['recommends'] ?? ($remote['recommends'] ?? [])),
                'conflicts' => self::words($custom['conflicts'] ?? ($remote['conflicts'] ?? [])),
                'minimum_security_level' => sanitize_key((string) ($custom['security'] ?? ($remote['minimum_security_level'] ?? ''))),
                'llm_safe_actions' => self::words($custom['llm'] ?? ($remote['llm_safe_actions'] ?? [])),
                'api_manifest' => esc_url_raw((string) ($custom['api_manifest'] ?? ($remote['api_manifest'] ?? ''))),
                'registration_capabilities' => self::registration_capabilities((array) ($registered[$slug]['capabilities'] ?? [])),
            ];
        }

        foreach ($available as $slug => $item) {
            if (isset($extensions[$slug])) {
                continue;
            }
            $extensions[$slug] = [
                'slug' => $slug,
                'name' => sanitize_text_field((string) ($item['name'] ?? $slug)),
                'version' => sanitize_text_field((string) ($item['version'] ?? '')),
                'installed' => false,
                'active' => false,
                'available' => true,
                'bundled' => !empty($item['bundled']),
                'commercial' => !empty($item['license_required']) ? 'paid' : 'unknown',
                'requires_service' => !empty($item['requires_service']),
                'provides' => self::words($item['provides'] ?? []),
                'requires' => self::words($item['requires'] ?? []),
                'recommends' => self::words($item['recommends'] ?? []),
                'conflicts' => self::words($item['conflicts'] ?? []),
                'minimum_security_level' => sanitize_key((string) ($item['minimum_security_level'] ?? '')),
                'llm_safe_actions' => self::words($item['llm_safe_actions'] ?? []),
                'api_manifest' => esc_url_raw((string) ($item['api_manifest'] ?? '')),
                'registration_capabilities' => [],
            ];
        }

        ksort($extensions);
        $endpoint_manifest = apply_filters('million-dollar-script/api/endpoint/manifest', []);
        $endpoint_manifest = is_array($endpoint_manifest) ? array_values($endpoint_manifest) : [];

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => gmdate('c'),
            'source' => [
                'runtime' => 'local',
                'available_catalog' => !empty($cache['refreshed_at']) ? 'explicit-cache' : 'unknown',
                'available_catalog_refreshed_at' => sanitize_text_field((string) ($cache['refreshed_at'] ?? '')),
                'last_error' => sanitize_text_field((string) ($cache['error'] ?? '')),
            ],
            'core' => [
                'version' => Runtime::version(),
                'extension_api_version' => Runtime::api_version(),
                'ready' => Runtime::is_ready(),
            ],
            'extensions' => array_values($extensions),
            'active_capabilities' => self::active_capabilities($extensions),
            'endpoints' => $endpoint_manifest,
        ];
    }

    /**
     * Explicit administrator refresh. Uses public core REST routes in-process.
     *
     * @return array<string,mixed>
     */
    public static function refresh(): array {
        $result = ['refreshed_at' => gmdate('c'), 'responses' => [], 'error' => ''];
        foreach (['/million-dollar-script/v1/extensions', '/million-dollar-script/v1/extensions/capabilities', '/million-dollar-script/v1/extensions/discovery'] as $route) {
            $response = rest_do_request(new \WP_REST_Request('GET', $route));
            if ($response->is_error()) {
                $result['error'] = $response->as_error()->get_error_message();
                break;
            }
            $result['responses'][$route] = $response->get_data();
        }
        update_option(Settings::DISCOVERY_CACHE, $result, false);
        return $result;
    }

    /**
     * @param array<string,mixed> $cache
     * @return array<string,array<string,mixed>>
     */
    private static function available_map(array $cache): array {
        $payload = $cache['responses']['/million-dollar-script/v1/extensions'] ?? [];
        $available = is_array($payload) ? ($payload['available'] ?? []) : [];
        $map = [];
        foreach ((array) $available as $item) {
            if (!is_array($item)) {
                continue;
            }
            $slug = sanitize_key((string) ($item['slug'] ?? ''));
            if ($slug) {
                $map[$slug] = $item;
            }
        }
        return $map;
    }

    /**
     * @param array<string,array<string,mixed>> $extensions
     * @return array<int,string>
     */
    private static function active_capabilities(array $extensions): array {
        $capabilities = [];
        foreach ($extensions as $extension) {
            if (!empty($extension['active'])) {
                $capabilities = array_merge($capabilities, (array) $extension['provides']);
            }
        }
        $capabilities = array_values(array_unique(array_map('sanitize_text_field', $capabilities)));
        sort($capabilities);
        return $capabilities;
    }

    /**
     * @param mixed $value
     * @return array<int,string>
     */
    private static function words($value): array {
        $values = is_array($value) ? $value : preg_split('/[\s,]+/', trim((string) $value));
        $values = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array) $values))));
        sort($values);
        return $values;
    }

    private static function yes(string $value): bool {
        return in_array(strtolower(trim($value)), ['1', 'yes', 'true'], true);
    }

    /**
     * @param array<int|string,mixed> $capabilities
     * @return array<int,string>
     */
    private static function registration_capabilities(array $capabilities): array {
        $normalized = [];
        foreach ($capabilities as $key => $value) {
            if (is_string($key)) {
                if (!empty($value)) {
                    $normalized[] = sanitize_text_field($key);
                }
                continue;
            }
            if (is_string($value) && '' !== trim($value)) {
                $normalized[] = sanitize_text_field($value);
            }
        }
        $normalized = array_values(array_unique($normalized));
        sort($normalized);
        return $normalized;
    }
}
