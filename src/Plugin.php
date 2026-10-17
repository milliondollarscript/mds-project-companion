<?php
/**
 * Extension bootstrap and public Million Dollar Script integrations.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion;

use MillionDollarScript\Core\Runtime;
use MillionDollarScript\Extensions\Registry as MdsRegistry;
use MillionDollarScript\Extensions\ProjectCompanion\Admin\AdminController;
use MillionDollarScript\Extensions\ProjectCompanion\Audit\AuditRepository;
use MillionDollarScript\Extensions\ProjectCompanion\Lifecycle\Installer;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin {
    private static ?self $instance = null;

    public static function boot(): void {
        if (self::$instance instanceof self) {
            return;
        }

        if (!self::environment_supported()) {
            add_action('admin_notices', [self::class, 'environment_notice']);
            return;
        }

        self::$instance = new self();
        self::$instance->register_hooks();
    }

    public static function activate(): void {
        if (!self::environment_supported()) {
            if (function_exists('deactivate_plugins')) {
                deactivate_plugins(MDS_PROJECT_COMPANION_BASENAME);
            }
            wp_die(
                esc_html__('Project Companion requires a ready Million Dollar Script installation with extension API major 1.', 'mds-project-companion'),
                esc_html__('Project Companion activation stopped', 'mds-project-companion'),
                ['back_link' => true]
            );
        }

        Installer::install();
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook(Installer::CLEANUP_HOOK);
    }

    public static function environment_supported(): bool {
        if (!class_exists(Runtime::class) || !Runtime::is_ready()) {
            return false;
        }

        $api_version = trim((string) Runtime::api_version());
        return 1 === (int) preg_replace('/[^0-9].*$/', '', $api_version);
    }

    public static function environment_notice(): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $message = class_exists(Runtime::class)
            ? __('Project Companion is inactive because Million Dollar Script is not ready or its extension API major is unsupported. API major 1 is required.', 'mds-project-companion')
            : __('Project Companion requires Million Dollar Script to be installed and active.', 'mds-project-companion');

        echo '<div class="notice notice-error"><p><strong>';
        echo esc_html__('Million Dollar Script - Project Companion', 'mds-project-companion');
        echo ':</strong> ' . esc_html($message) . '</p></div>';
    }

    private function register_hooks(): void {
        add_action('million-dollar-script/register/extensions', [$this, 'register_extension']);
        add_action('million-dollar-script/admin/menu', [AdminController::class, 'register_menu']);
        add_action('admin_menu', [AdminController::class, 'register_fallback_menu'], 40);
        add_action('admin_enqueue_scripts', [AdminController::class, 'enqueue_assets']);
        add_action('admin_init', [Installer::class, 'maybe_upgrade'], 5);
        add_action('admin_init', [AdminController::class, 'maybe_activation_redirect']);
        add_action('admin_post_mds_project_companion_save', [AdminController::class, 'save']);
        add_action('admin_post_mds_project_companion_refresh', [AdminController::class, 'refresh_discovery']);
        add_action('admin_post_mds_project_companion_compatibility', [AdminController::class, 'check_compatibility']);
        add_action('admin_post_mds_project_companion_begin_pairing', [AdminController::class, 'begin_pairing']);
        add_action('admin_post_mds_project_companion_complete_pairing', [AdminController::class, 'complete_pairing']);
        add_action('admin_post_mds_project_companion_test_connection', [AdminController::class, 'test_connection']);
        add_action('admin_post_mds_project_companion_rotate', [AdminController::class, 'rotate_credential']);
        add_action('admin_post_mds_project_companion_revoke', [AdminController::class, 'revoke_connection']);
        add_action('admin_post_mds_project_companion_preview', [AdminController::class, 'create_preview']);
        add_action('admin_post_mds_project_companion_approve', [AdminController::class, 'approve_preview']);
        add_action('admin_post_mds_project_companion_fetch_view', [AdminController::class, 'fetch_hosted_view']);
        add_action('admin_post_mds_project_companion_acknowledge', [AdminController::class, 'acknowledge_action']);
        add_action('admin_post_mds_project_companion_clear', [AdminController::class, 'clear_data']);
        add_action(Installer::CLEANUP_HOOK, [AuditRepository::class, 'cleanup_expired']);
        add_filter('million-dollar-script/extension/onboarding/items', [$this, 'onboarding_items']);
        add_filter('million-dollar-script/docs/manifest/paths', [$this, 'docs_manifest_paths']);
    }

    public function register_extension(): void {
        MdsRegistry::register([
            'id' => 'mds-project-companion',
            'name' => 'Project Companion',
            'description' => 'Plan projects, discover suitable extensions, and control consent-bound hosted sharing.',
            'cleanup' => [
                'description' => 'Project settings, connections, consent records, discovery cache, secrets, and audit records.',
            ],
            'version' => MDS_PROJECT_COMPANION_VERSION,
            'file' => MDS_PROJECT_COMPANION_FILE,
            'main_file' => MDS_PROJECT_COMPANION_FILE,
            'premium' => false,
            'license_required' => false,
            'capabilities' => [
                'admin_pages' => true,
                'capability_discovery' => true,
                'recommendations' => true,
                'consent_preview' => true,
                'audit_history' => true,
                'hosted_connector_v1' => true,
                'hosted_plan_read' => true,
                'hosted_create_read' => true,
                'hosted_distribution_read' => true,
                'hosted_growth_read' => true,
            ],
        ]);
    }

    /**
     * @param array<string,array<string,mixed>> $items
     * @return array<string,array<string,mixed>>
     */
    public function onboarding_items(array $items): array {
        $items['mds-project-companion'] = [
            'name' => __('Project Companion', 'mds-project-companion'),
            'summary' => __('Describe your project, choose optional modules, and review local extension recommendations.', 'mds-project-companion'),
            'priority' => 45,
            'always_show' => true,
            'actions' => [[
                'label' => __('Open setup', 'mds-project-companion'),
                'url' => admin_url('admin.php?page=mds-project-companion&tab=overview&setup=1'),
                'primary' => true,
            ]],
            'setup_pages' => [],
            'legal_documents' => [],
        ];

        return $items;
    }

    /**
     * @param array<int,string> $paths
     * @return array<int,string>
     */
    public function docs_manifest_paths(array $paths): array {
        $path = MDS_PROJECT_COMPANION_PATH . 'docs/manifest.json';
        if (is_readable($path) && !in_array($path, $paths, true)) {
            $paths[] = $path;
        }
        return $paths;
    }
}
