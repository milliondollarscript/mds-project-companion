<?php
/**
 * Plugin Name:       Million Dollar Script - Project Companion
 * Plugin URI:        https://milliondollarscript.com/extensions/project-companion
 * Description:       Plan Million Dollar Script projects locally, review extension recommendations, and share approved context through a consent-bound hosted connector.
 * Version:           0.2.0
 * Requires at least: 6.0
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Requires Plugins:  million-dollar-script
 * Author:            Million Dollar Script
 * Author URI:        https://milliondollarscript.com
 * License:           GPL-3.0+
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       mds-project-companion
 * Domain Path:       /languages
 * Requires MDS:      3.0.0
 * MDS Compatible:    MDS 3.0
 * MDS Provides:      integration.project-companion planning.recommendations consent.preview
 * MDS Requires:      platform.core
 * MDS Requires Service: no
 * MDS Setup Category: integration
 * MDS Minimum Security Level: manage_options
 * Requires MDS API:  1
 * MDS Generation:    3
 * MDS Icon:          route
 * MDS Accent Color:  #2563eb
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion;

if (!defined('ABSPATH')) {
    exit;
}

define('MDS_PROJECT_COMPANION_VERSION', '0.2.0');
define('MDS_PROJECT_COMPANION_DB_VERSION', '2');
define('MDS_PROJECT_COMPANION_FILE', __FILE__);
define('MDS_PROJECT_COMPANION_PATH', plugin_dir_path(__FILE__));
define('MDS_PROJECT_COMPANION_URL', plugin_dir_url(__FILE__));
define('MDS_PROJECT_COMPANION_BASENAME', plugin_basename(__FILE__));

spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';
    if (0 !== strpos($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = MDS_PROJECT_COMPANION_PATH . 'src/' . $relative . '.php';
    if (is_readable($path)) {
        require_once $path;
    }
});

register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);
add_action('plugins_loaded', [Plugin::class, 'boot'], 20);
