<?php
/**
 * WP-CLI integration fixture for an installed local extension.
 */

if (!defined('ABSPATH')) {
    exit(1);
}

$failures = [];
$assert = static function ($condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$settings = get_option('mds_project_companion_settings', []);
$assert(is_array($settings), 'Settings option was not installed.');
$assert(isset($settings['modules']) && !array_filter($settings['modules']), 'Modules must default to disabled.');
$assert((bool) wp_next_scheduled('mds_project_companion_cleanup'), 'Bounded cleanup schedule is missing.');

global $wpdb;
$table = $wpdb->prefix . 'mds_project_companion_audit';
$assert($table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)), 'Audit table is missing.');

do_action('million-dollar-script/register/extensions');
$registered = \MillionDollarScript\Extensions\Registry::registered();
$assert(isset($registered['mds-project-companion']), 'Public MDS registry entry is missing.');
$assert(isset(\MillionDollarScript\Extensions\CleanupPolicy::registered()['mds-project-companion']), 'Public cleanup policy entry is missing.');
$assert(\MillionDollarScript\Extensions\CleanupPolicy::is_included('mds-project-companion'), 'Cleanup policy must default to selected.');

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Project Companion WordPress fixture passed.\n";
