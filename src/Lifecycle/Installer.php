<?php
/**
 * Extension-owned installation and cleanup scheduling.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Lifecycle;

use MillionDollarScript\Extensions\ProjectCompanion\Audit\AuditRepository;
use MillionDollarScript\Extensions\ProjectCompanion\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Installer {
    public const DB_VERSION_OPTION = 'mds_project_companion_db_version';
    public const CLEANUP_HOOK = 'mds_project_companion_cleanup';

    public static function install(): void {
        add_option(Settings::OPTION, Settings::defaults(), '', false);
        update_option(Settings::ACTIVATION_REDIRECT, (string) time(), false);
        AuditRepository::install();
        update_option(self::DB_VERSION_OPTION, MDS_PROJECT_COMPANION_DB_VERSION, false);

        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK);
        }
    }

    public static function maybe_upgrade(): void {
        $installed = (int) get_option(self::DB_VERSION_OPTION, 0);
        if ($installed >= (int) MDS_PROJECT_COMPANION_DB_VERSION) {
            return;
        }
        $settings = Settings::get();
        $sharing = (array) ($settings['sharing'] ?? []);
        $map = [
            'project_name' => ['project.name'],
            'project_summary' => ['project.summary'],
            'project_goals' => ['project.goals'],
            'project_focus' => ['project.type'],
            'funding_preference' => ['project.constraints'],
            'mds_versions' => ['core_api_version', 'extension_api_version'],
            'extension_capabilities' => ['extensions', 'public_capabilities'],
        ];
        foreach ($map as $legacy => $replacements) {
            if (empty($sharing[$legacy])) {
                unset($sharing[$legacy]);
                continue;
            }
            foreach ($replacements as $replacement) {
                $sharing[$replacement] = true;
            }
            unset($sharing[$legacy]);
        }
        $settings['sharing'] = $sharing;
        update_option(Settings::OPTION, Settings::sanitize($settings), false);
        update_option(self::DB_VERSION_OPTION, MDS_PROJECT_COMPANION_DB_VERSION, false);
    }
}
