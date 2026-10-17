<?php
/**
 * Isolated bootstrap readiness probe.
 */

declare(strict_types=1);

$scenario = $argv[1] ?? 'missing';
define('ABSPATH', __DIR__ . '/');
define('MDS_PROJECT_COMPANION_VERSION', '0.2.0');
define('MDS_PROJECT_COMPANION_FILE', dirname(__DIR__) . '/mds-project-companion.php');
define('MDS_PROJECT_COMPANION_PATH', dirname(__DIR__) . '/');
define('MDS_PROJECT_COMPANION_URL', 'http://example.test/plugin/');
define('MDS_PROJECT_COMPANION_BASENAME', 'mds-project-companion/mds-project-companion.php');

$GLOBALS['actions'] = [];
function add_action($hook, $callback, $priority = 10) { $GLOBALS['actions'][] = $hook; }
function add_filter($hook, $callback, $priority = 10) { $GLOBALS['actions'][] = $hook; }

if ('missing' !== $scenario) {
    $ready = 'unready' !== $scenario ? 'true' : 'false';
    $api = 'unsupported' === $scenario ? "'2'" : "'1'";
    eval("namespace MillionDollarScript\\Core; final class Runtime { public static function is_ready(): bool { return {$ready}; } public static function api_version(): string { return {$api}; } }");
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MillionDollarScript\\Extensions\\ProjectCompanion\\';
    if (0 !== strpos($class, $prefix)) {
        return;
    }
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($path)) {
        require_once $path;
    }
});

require_once dirname(__DIR__) . '/src/Plugin.php';
\MillionDollarScript\Extensions\ProjectCompanion\Plugin::boot();

echo json_encode([
    'scenario' => $scenario,
    'notice' => in_array('admin_notices', $GLOBALS['actions'], true),
    'registered' => in_array('million-dollar-script/register/extensions', $GLOBALS['actions'], true),
]);
