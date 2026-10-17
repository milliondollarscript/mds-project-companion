<?php
/**
 * Extension-owned settings and profile validation.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion;

if (!defined('ABSPATH')) {
    exit;
}

final class Settings {
    public const OPTION = 'mds_project_companion_settings';
    public const ACTIVATION_REDIRECT = 'mds_project_companion_activation_redirect';
    public const DISCOVERY_CACHE = 'mds_project_companion_discovery_cache';
    public const PENDING_PREVIEW_PREFIX = 'mds_project_companion_preview_';

    /**
     * @return array<string,mixed>
     */
    public static function defaults(): array {
        return [
            'modules' => ['plan' => false, 'create' => false, 'distribute' => false, 'grow' => false],
            'sharing' => [],
            'profile' => [
                'name' => '',
                'summary' => '',
                'goals' => [],
                'focus' => 'unspecified',
                'funding' => 'unspecified',
                'external_services' => 'undecided',
                'setup_effort' => 'moderate',
            ],
            'retention_days' => 365,
            'setup_completed' => false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function get(): array {
        $stored = get_option(self::OPTION, []);
        return self::sanitize(wp_parse_args(is_array($stored) ? $stored : [], self::defaults()));
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function sanitize(array $input): array {
        $defaults = self::defaults();
        $modules = is_array($input['modules'] ?? null) ? $input['modules'] : [];
        $profile = is_array($input['profile'] ?? null) ? $input['profile'] : [];
        $sharing = is_array($input['sharing'] ?? null) ? $input['sharing'] : [];
        $allowed_goals = ['funding', 'local', 'sponsorship', 'recurring', 'launches', 'missions', 'bounties', 'tools', 'distribution', 'analytics', 'growth'];
        $goals = array_values(array_intersect($allowed_goals, array_map('sanitize_key', (array) ($profile['goals'] ?? []))));
        $clean_sharing = [];
        foreach ($sharing as $key => $enabled) {
            $key = strtolower(trim((string) $key));
            if (preg_match('/^[a-z][a-z0-9_.-]{0,119}$/', $key)) {
                $clean_sharing[$key] = !empty($enabled);
            }
        }

        $retention = (string) ($input['retention_days'] ?? '365');
        $retention_days = 'indefinite' === $retention ? 0 : min(3650, max(30, absint($retention)));

        return [
            'modules' => [
                'plan' => !empty($modules['plan']),
                'create' => !empty($modules['create']),
                'distribute' => !empty($modules['distribute']),
                'grow' => !empty($modules['grow']),
            ],
            'sharing' => $clean_sharing,
            'profile' => [
                'name' => mb_substr(sanitize_text_field((string) ($profile['name'] ?? '')), 0, 160),
                'summary' => mb_substr(sanitize_textarea_field((string) ($profile['summary'] ?? '')), 0, 2000),
                'goals' => $goals,
                'focus' => self::enum((string) ($profile['focus'] ?? ''), ['local', 'online', 'hybrid', 'unspecified'], $defaults['profile']['focus']),
                'funding' => self::enum((string) ($profile['funding'] ?? ''), ['none', 'one-time', 'recurring', 'both', 'unspecified'], $defaults['profile']['funding']),
                'external_services' => self::enum((string) ($profile['external_services'] ?? ''), ['yes', 'no', 'undecided'], $defaults['profile']['external_services']),
                'setup_effort' => self::enum((string) ($profile['setup_effort'] ?? ''), ['simple', 'moderate', 'advanced'], $defaults['profile']['setup_effort']),
            ],
            'retention_days' => $retention_days,
            'setup_completed' => !empty($input['setup_completed']),
        ];
    }

    /**
     * @param array<int,string> $allowed
     */
    private static function enum(string $value, array $allowed, string $fallback): string {
        $value = sanitize_key($value);
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
