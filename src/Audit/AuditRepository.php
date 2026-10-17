<?php
/**
 * Redacted audit event persistence.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Audit;

use MillionDollarScript\Extensions\ProjectCompanion\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class AuditRepository {
    private const ALLOWED_EVENTS = [
        'module_enabled', 'module_disabled', 'connection_initiated', 'connection_completed',
        'connection_failed', 'connection_revoked', 'payload_previewed', 'export_approved',
        'export_denied', 'remote_request_failed', 'response_rejected', 'credential_rotated',
        'hosted_cache_cleared', 'discovery_refreshed', 'settings_updated', 'data_cleared',
        'compatibility_checked', 'pairing_challenge_received', 'connection_tested',
        'hosted_view_fetched', 'action_acknowledged',
    ];

    public static function table(): string {
        global $wpdb;
        return $wpdb->prefix . 'mds_project_companion_audit';
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(64) NOT NULL,
            module varchar(32) NOT NULL DEFAULT '',
            outcome varchar(32) NOT NULL DEFAULT '',
            request_id varchar(64) NOT NULL DEFAULT '',
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            subject_hash char(64) NOT NULL DEFAULT '',
            details_json text NOT NULL,
            created_at datetime NOT NULL,
            expires_at datetime NULL,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY expires_at (expires_at)
        ) {$charset};");
    }

    /**
     * @param array<string,mixed> $details
     */
    public static function record(string $event, string $module = '', string $outcome = '', array $details = [], string $request_id = '', string $subject = ''): bool {
        if (!in_array($event, self::ALLOWED_EVENTS, true)) {
            return false;
        }

        global $wpdb;
        $settings = Settings::get();
        $days = absint($settings['retention_days'] ?? 365);
        $created = current_time('mysql', true);
        $expires = $days > 0 ? gmdate('Y-m-d H:i:s', time() + ($days * DAY_IN_SECONDS)) : null;
        $json = wp_json_encode(Redactor::clean($details));
        if (!is_string($json)) {
            $json = '{}';
        }
        $json = mb_substr($json, 0, 4000);

        return false !== $wpdb->insert(self::table(), [
            'event_type' => sanitize_key($event),
            'module' => sanitize_key($module),
            'outcome' => sanitize_key($outcome),
            'request_id' => mb_substr(sanitize_text_field($request_id), 0, 64),
            'actor_user_id' => get_current_user_id(),
            'subject_hash' => '' === $subject ? '' : hash('sha256', wp_salt('auth') . $subject),
            'details_json' => $json,
            'created_at' => $created,
            'expires_at' => $expires,
        ], ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 50, int $offset = 0): array {
        global $wpdb;
        $limit = min(100, max(1, $limit));
        $offset = max(0, $offset);
        $table = self::table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, event_type, module, outcome, request_id, actor_user_id, subject_hash, details_json, created_at
             FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public static function cleanup_expired(): int {
        global $wpdb;
        $table = self::table();
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE expires_at IS NOT NULL AND expires_at <= %s ORDER BY id ASC LIMIT 500",
            current_time('mysql', true)
        ));
        $ids = array_values(array_filter(array_map('absint', is_array($ids) ? $ids : [])));
        if (!$ids) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return (int) $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$ids));
    }

    public static function purge(): int {
        global $wpdb;
        return (int) $wpdb->query('DELETE FROM ' . self::table());
    }
}
