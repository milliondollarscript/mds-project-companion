<?php
/**
 * Focused private API and package-boundary scan.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$forbidden = [
    'private MDS namespace' => '/MillionDollarScript\\\\V3\\\\/',
    'legacy MDS namespace' => '/MDS\\\\Extensions\\\\/',
    'archived connector' => '/mds-creator-platforms/',
    'private option access' => '/get_option\\([\'"](?:mds_revenue_agent|mds_missions|mds_launch_wall|mds_sponsorboard)/',
    'private table access' => '/\\$wpdb->prefix\\s*\\.\\s*[\'"](?:mds_revenue_agent|mds_missions|mds_launch_wall|mds_sponsorboard)/',
];

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js'], true)) {
        continue;
    }
    if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    foreach ($forbidden as $label => $pattern) {
        if (preg_match($pattern, $contents)) {
            $failures[] = "{$label}: " . substr($file->getPathname(), strlen($root) + 1);
        }
    }
}

$admin_controller = (string) file_get_contents($root . '/src/Admin/AdminController.php');
$protected_actions = [
    'save' => 'mds_project_companion_save',
    'refresh_discovery' => 'mds_project_companion_refresh',
    'check_compatibility' => 'mds_project_companion_compatibility',
    'begin_pairing' => 'mds_project_companion_begin_pairing',
    'complete_pairing' => 'mds_project_companion_complete_pairing',
    'test_connection' => 'mds_project_companion_test_connection',
    'rotate_credential' => 'mds_project_companion_rotate',
    'revoke_connection' => 'mds_project_companion_revoke',
    'create_preview' => 'mds_project_companion_preview',
    'approve_preview' => 'mds_project_companion_approve',
    'fetch_hosted_view' => 'mds_project_companion_fetch_view',
    'acknowledge_action' => 'mds_project_companion_acknowledge',
    'clear_data' => 'mds_project_companion_clear',
];
foreach ($protected_actions as $method => $nonce_action) {
    $pattern = '/public static function ' . preg_quote($method, '/') . '\(\): void \{(?P<body>.*?)(?=\n    public static function|\n    private static function)/s';
    if (!preg_match($pattern, $admin_controller, $match)) {
        $failures[] = "missing protected admin action: {$method}";
        continue;
    }
    if (!str_contains($match['body'], 'self::authorize();')) {
        $failures[] = "missing capability guard: {$method}";
    }
    if (!str_contains($match['body'], "check_admin_referer('{$nonce_action}')")) {
        $failures[] = "missing nonce guard: {$method}";
    }
}

if (preg_match('/public static function render\(\): void \{(?P<body>.*?)(?=\n    public static function)/s', $admin_controller, $render)) {
    if (str_contains($render['body'], 'ClientFactory::make(')) {
        $failures[] = 'normal admin rendering must not instantiate or call the hosted client';
    }
} else {
    $failures[] = 'missing admin render method';
}

$http_callers = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || 'php' !== strtolower($file->getExtension())) {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    if (preg_match('/\bwp_(?:safe_)?remote_(?:request|get|post)\s*\(/', $contents)) {
        $http_callers[] = substr($file->getPathname(), strlen($root) + 1);
    }
}
if (['src/Platform/Http/WordPressTransport.php'] !== $http_callers) {
    $failures[] = 'hosted HTTP calls must remain centralized in WordPressTransport';
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Project Companion private-contract scan passed.\n";
