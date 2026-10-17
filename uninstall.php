<?php
/**
 * Project Companion uninstall cleanup.
 *
 * Data is removed only when the public core parent policy is enabled and this
 * extension remains included. When policy state cannot be resolved, preserve it.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!class_exists('\MillionDollarScript\Extensions\CleanupPolicy')) {
    return;
}
\MillionDollarScript\Extensions\CleanupPolicy::cleanup('mds-project-companion', [
    'tables' => ['mds_project_companion_audit'],
    'option_prefixes' => ['mds_project_companion_'],
    'transient_prefixes' => ['mds_project_companion_'],
    'cron_hooks' => ['mds_project_companion_cleanup'],
]);
