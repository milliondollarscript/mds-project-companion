<?php
/**
 * Project Companion administration controller.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Admin;

use MillionDollarScript\Extensions\Support;
use MillionDollarScript\Extensions\ProjectCompanion\Audit\AuditRepository;
use MillionDollarScript\Extensions\ProjectCompanion\Discovery\Snapshot;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\ClientFactory;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\ClientResult;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\Compatibility;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\ConnectionRepository;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\ConnectorContract;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\HostedServiceConfig;
use MillionDollarScript\Extensions\ProjectCompanion\Recommendations\Engine;
use MillionDollarScript\Extensions\ProjectCompanion\Settings;
use MillionDollarScript\Extensions\ProjectCompanion\Sharing\FieldRegistry;
use MillionDollarScript\Extensions\ProjectCompanion\Sharing\Preview;
use MillionDollarScript\Extensions\ProjectCompanion\Sharing\ContractPayload;
use MillionDollarScript\Core\Runtime;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminController {
    private const PAGE = 'mds-project-companion';
    private static bool $menu_registered = false;

    public static function register_menu(): void {
        if (self::$menu_registered) {
            return;
        }
        self::$menu_registered = true;
        $parent = class_exists(Support::class) ? Support::admin_parent_slug('options-general.php') : 'options-general.php';
        add_submenu_page(
            $parent,
            __('Project Companion', 'mds-project-companion'),
            __('Project Companion', 'mds-project-companion'),
            'manage_options',
            self::PAGE,
            [self::class, 'render']
        );
    }

    public static function register_fallback_menu(): void {
        self::register_menu();
    }

    public static function enqueue_assets(string $hook): void {
        if (false === strpos($hook, self::PAGE)) {
            return;
        }
        if (class_exists('\MillionDollarScript\Core\Runtime')) {
            wp_enqueue_style('million-dollar-script-admin', \MillionDollarScript\Core\Runtime::url('assets/mds3/css/admin.css'), [], \MillionDollarScript\Core\Runtime::version());
        }
        wp_enqueue_style('mds-project-companion-admin', MDS_PROJECT_COMPANION_URL . 'assets/css/admin.css', [], MDS_PROJECT_COMPANION_VERSION);
        wp_enqueue_script('mds-project-companion-admin', MDS_PROJECT_COMPANION_URL . 'assets/js/admin.js', [], MDS_PROJECT_COMPANION_VERSION, true);
    }

    public static function maybe_activation_redirect(): void {
        if (
            !current_user_can('manage_options')
            || wp_doing_ajax()
            || (defined('WP_CLI') && WP_CLI)
            || is_network_admin()
            || isset($_GET['activate-multi'])
        ) {
            return;
        }
        $activated = absint(get_option(Settings::ACTIVATION_REDIRECT, 0));
        if (!$activated || $activated < (time() - WEEK_IN_SECONDS) || get_user_meta(get_current_user_id(), 'mds_project_companion_setup_seen', true)) {
            return;
        }
        update_user_meta(get_current_user_id(), 'mds_project_companion_setup_seen', '1');
        wp_safe_redirect(admin_url('admin.php?page=' . self::PAGE . '&tab=overview&setup=1'));
        exit;
    }

    public static function render(): void {
        self::authorize();
        $tabs = ['overview', 'connection', 'modules', 'recommendations', 'shared-data', 'activity', 'diagnostics', 'documentation'];
        $tab = sanitize_key((string) ($_GET['tab'] ?? 'overview'));
        $tab = in_array($tab, $tabs, true) ? $tab : 'overview';
        $filter = sanitize_key((string) ($_GET['recommendation_filter'] ?? 'best-fit'));
        $filter = in_array($filter, ['best-fit', 'installed', 'free', 'no-services'], true) ? $filter : 'best-fit';
        $settings = Settings::get();
        $snapshot = Snapshot::build();
        $recommendations = Engine::recommend($settings['profile'], $snapshot, $filter);
        $preview = get_transient(Settings::PENDING_PREVIEW_PREFIX . get_current_user_id());
        $preview = is_array($preview) ? $preview : [];
        $activity = 'activity' === $tab ? AuditRepository::recent(50) : [];
        $hosted = HostedServiceConfig::get();
        $connections = new ConnectionRepository();
        $connection_state = $connections->state();
        $connection = new ClientResult(
            $connections->connected(),
            (string) ($connection_state['status'] ?? 'disconnected'),
            '',
            $connection_state,
            (string) ($connection_state['last_error_code'] ?? ''),
            $connections->connected()
                ? __('Connected. Use Test connection to perform an explicit hosted check.', 'mds-project-companion')
                : __('Project Companion remains fully usable locally while disconnected.', 'mds-project-companion')
        );
        $pairing = $connections->pairing(get_current_user_id());
        $required_scopes = ConnectorContract::scopes_for_modules($settings['modules']);
        $missing_scopes = array_values(array_diff($required_scopes, (array) ($connection_state['scopes'] ?? [])));
        $hosted_views = [
            'plan' => $connections->view('plan'),
            'recommendations' => $connections->view('recommendations'),
            'create' => $connections->view('create'),
            'distribution' => $connections->view('distribution'),
            'growth' => $connections->view('growth'),
        ];
        $fields = FieldRegistry::all();
        $template = MDS_PROJECT_COMPANION_PATH . 'templates/admin-page.php';
        require $template;
    }

    public static function save(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_save');
        $old = Settings::get();
        $input = isset($_POST['settings']) && is_array($_POST['settings']) ? wp_unslash($_POST['settings']) : [];
        $input['setup_completed'] = !empty($_POST['setup_completed']) || !empty($old['setup_completed']);
        $settings = Settings::sanitize($input);
        update_option(Settings::OPTION, $settings, false);

        foreach ($settings['modules'] as $module => $enabled) {
            if ((bool) ($old['modules'][$module] ?? false) !== (bool) $enabled) {
                AuditRepository::record($enabled ? 'module_enabled' : 'module_disabled', $module, 'success');
            }
        }
        AuditRepository::record('settings_updated', '', 'success', ['sections' => ['modules', 'profile', 'sharing', 'retention', 'cleanup']]);
        self::redirect(self::request_tab(), 'saved');
    }

    public static function refresh_discovery(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_refresh');
        $result = Snapshot::refresh();
        AuditRepository::record('discovery_refreshed', '', empty($result['error']) ? 'success' : 'failure', [
            'routes' => array_keys((array) ($result['responses'] ?? [])),
            'error_code' => empty($result['error']) ? '' : 'public_discovery_failed',
        ]);
        self::redirect('diagnostics', empty($result['error']) ? 'refreshed' : 'refresh-failed');
    }

    public static function check_compatibility(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_compatibility');
        $settings = Settings::get();
        $required_scopes = ConnectorContract::scopes_for_modules($settings['modules']);
        $result = ClientFactory::make()->discover_capabilities();
        $connections = new ConnectionRepository();
        if (!$result->success) {
            self::record_client_failure($result);
            $connections->store_compatibility([
                'compatible' => false,
                'reasons' => [$result->error_code ?: 'discovery_failed'],
            ]);
            AuditRepository::record('connection_failed', '', 'failure', ['error_code' => $result->error_code], $result->request_id);
            self::redirect('connection', 'compatibility-failed');
        }
        $compatibility = Compatibility::evaluate($result->data, $required_scopes);
        $connections->store_compatibility($compatibility);
        AuditRepository::record(
            'compatibility_checked',
            '',
            $compatibility['compatible'] ? 'success' : 'failure',
            ['reasons' => $compatibility['reasons']]
        );
        self::redirect('connection', $compatibility['compatible'] ? 'compatible' : 'incompatible');
    }

    public static function begin_pairing(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_begin_pairing');
        $token = trim((string) wp_unslash($_POST['platform_token'] ?? ''));
        $project_id = strtolower(trim((string) wp_unslash($_POST['project_id'] ?? '')));
        if (
            '' === $token
            || 1 !== preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $project_id)
        ) {
            AuditRepository::record('connection_failed', '', 'failure', ['error_code' => 'invalid_pairing_input']);
            self::redirect('connection', 'pairing-input-failed');
        }

        $settings = Settings::get();
        $scopes = ConnectorContract::scopes_for_modules($settings['modules']);
        $client = ClientFactory::make();
        $discovery = $client->discover_capabilities();
        if (!$discovery->success) {
            self::record_client_failure($discovery);
            AuditRepository::record('connection_failed', '', 'failure', ['error_code' => $discovery->error_code], $discovery->request_id);
            self::redirect('connection', 'compatibility-failed');
        }
        $compatibility = Compatibility::evaluate($discovery->data, $scopes);
        $connections = new ConnectionRepository();
        $connections->store_compatibility($compatibility);
        if (!$compatibility['compatible']) {
            AuditRepository::record('connection_failed', '', 'failure', ['error_code' => 'contract_incompatible', 'reasons' => $compatibility['reasons']]);
            self::redirect('connection', 'incompatible');
        }

        AuditRepository::record('connection_initiated', '', 'pending', ['project_id' => self::redacted_identifier($project_id)]);
        $result = $client->begin_pairing([
            'platform_token' => $token,
            'payload' => [
                'installation_key' => $connections->installation_key(),
                'project_id' => $project_id,
                'connector_version' => MDS_PROJECT_COMPANION_VERSION,
                'core_version' => (string) Runtime::version(),
                'requested_contract' => ConnectorContract::VERSION,
                'required_schemas' => ConnectorContract::SCHEMAS,
                'requested_scopes' => $scopes,
            ],
        ]);
        unset($token);
        if (!$result->success) {
            self::record_client_failure($result);
            AuditRepository::record('connection_failed', '', 'failure', ['error_code' => $result->error_code], $result->request_id);
            self::redirect('connection', 'pairing-failed');
        }
        $connections->store_pairing(get_current_user_id(), $result->data + ['requested_scopes' => $scopes]);
        AuditRepository::record('pairing_challenge_received', '', 'success', [
            'pairing_id' => self::redacted_identifier((string) ($result->data['pairing_id'] ?? '')),
            'expires_at' => (string) ($result->data['expires_at'] ?? ''),
        ]);
        self::redirect('connection', 'pairing-started');
    }

    public static function complete_pairing(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_complete_pairing');
        $connections = new ConnectionRepository();
        $pairing = $connections->pairing(get_current_user_id());
        if (!$pairing) {
            self::redirect('connection', 'pairing-expired');
        }
        $result = ClientFactory::make()->complete_pairing([
            'pairing_id' => (string) $pairing['pairing_id'],
            'pairing_secret' => (string) $pairing['pairing_secret'],
            'payload' => [
                'requested_contract' => (string) $pairing['requested_contract'],
                'required_schemas' => (array) $pairing['required_schemas'],
            ],
            'required_scopes' => (array) ($pairing['requested_scopes'] ?? []),
        ]);
        if (!$result->success) {
            self::record_client_failure($result);
            AuditRepository::record('connection_failed', '', 'failure', ['error_code' => $result->error_code], $result->request_id);
            self::redirect('connection', 'pairing-not-approved');
        }
        $connections->connect((array) $result->data['installation'], (array) $result->data['credential']);
        $connections->clear_pairing(get_current_user_id());
        AuditRepository::record('connection_completed', '', 'success', [
            'installation_id' => self::redacted_identifier((string) $result->data['installation']['installation_id']),
            'contract_version' => (string) $result->data['installation']['contract_version'],
        ], $result->request_id);
        self::redirect('connection', 'connected');
    }

    public static function test_connection(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_test_connection');
        $result = ClientFactory::make()->test_connection();
        $connections = new ConnectionRepository();
        if ($result->success) {
            $connections->store_test($result->data);
        } else {
            self::record_client_failure($result);
            $connections->fail($result->error_code);
        }
        AuditRepository::record('connection_tested', '', $result->success ? 'success' : 'failure', ['error_code' => $result->error_code], $result->request_id);
        self::redirect('connection', $result->success ? 'connection-ok' : 'connection-failed');
    }

    public static function rotate_credential(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_rotate');
        $result = ClientFactory::make()->rotate_credential();
        if ($result->success) {
            (new ConnectionRepository())->rotate($result->data);
        } else {
            self::record_client_failure($result);
        }
        AuditRepository::record('credential_rotated', '', $result->success ? 'success' : 'failure', ['error_code' => $result->error_code], $result->request_id);
        self::redirect('connection', $result->success ? 'credential-rotated' : 'rotation-failed');
    }

    public static function revoke_connection(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_revoke');
        $result = ClientFactory::make()->revoke_connection(['reason' => 'administrator_disconnected']);
        $revoked = $result->success || 'installation_revoked' === $result->error_code;
        if ($revoked) {
            (new ConnectionRepository())->disconnect('revoked');
        } elseif (!$result->success) {
            self::record_client_failure($result);
        }
        AuditRepository::record('connection_revoked', '', $revoked ? 'success' : 'failure', ['error_code' => $result->error_code], $result->request_id);
        self::redirect('connection', $revoked ? 'revoked' : 'revocation-failed');
    }

    public static function create_preview(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_preview');
        $module = sanitize_key((string) ($_POST['module'] ?? ''));
        $field_ids = isset($_POST['field_ids']) && is_array($_POST['field_ids']) ? wp_unslash($_POST['field_ids']) : [];
        $preview = Preview::create($module, $field_ids, Snapshot::build());
        if (!$preview) {
            self::redirect('shared-data', 'preview-unavailable');
        }
        set_transient(Settings::PENDING_PREVIEW_PREFIX . get_current_user_id(), $preview, 15 * MINUTE_IN_SECONDS);
        AuditRepository::record('payload_previewed', $module, 'success', ['field_ids' => $preview['field_ids']], '', (string) $preview['hash']);
        self::redirect('shared-data', 'previewed');
    }

    public static function approve_preview(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_approve');
        $preview = get_transient(Settings::PENDING_PREVIEW_PREFIX . get_current_user_id());
        $submitted = sanitize_text_field((string) ($_POST['preview_hash'] ?? ''));
        if (!is_array($preview) || !Preview::valid($preview, $submitted) || empty($_POST['explicit_approval'])) {
            AuditRepository::record('export_denied', sanitize_key((string) ($preview['module'] ?? '')), 'denied', ['reason' => 'missing_or_stale_consent']);
            self::redirect('shared-data', 'consent-failed');
        }

        $module = (string) $preview['module'];
        $payload = ContractPayload::approved($preview);
        $client = ClientFactory::make();
        $result = 'capability_snapshot' === ($preview['transfer_kind'] ?? '')
            ? $client->send_capability_snapshot($payload)
            : $client->send_project_context($payload);
        if ($result->success) {
            (new ConnectionRepository())->record_transfer(
                (string) ($preview['transfer_kind'] ?? ''),
                (array) $preview['field_ids'],
                $result->request_id
            );
        } else {
            self::record_client_failure($result, $module);
        }
        AuditRepository::record(
            'export_approved',
            $module,
            $result->success ? 'success' : 'failure',
            ['field_ids' => $preview['field_ids'], 'client_status' => $result->status, 'error_code' => $result->error_code],
            $result->request_id,
            (string) $preview['hash']
        );
        delete_transient(Settings::PENDING_PREVIEW_PREFIX . get_current_user_id());
        self::redirect('shared-data', $result->success ? 'approved' : 'hosted-disabled');
    }

    public static function fetch_hosted_view(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_fetch_view');
        $view = sanitize_key((string) ($_POST['view'] ?? ''));
        $modules = [
            'plan' => 'plan',
            'recommendations' => 'plan',
            'create' => 'create',
            'distribution' => 'distribute',
            'growth' => 'grow',
        ];
        $module = $modules[$view] ?? '';
        $settings = Settings::get();
        if ('' === $module || empty($settings['modules'][$module])) {
            self::redirect('connection', 'module-disabled');
        }
        $client = ClientFactory::make();
        $result = match ($view) {
            'plan' => $client->fetch_plan(),
            'recommendations' => $client->fetch_recommendations(),
            'create' => $client->fetch_create(),
            'distribution' => $client->fetch_distribution(),
            'growth' => $client->fetch_growth(),
            default => new ClientResult(false, 'failed', '', [], 'invalid_view'),
        };
        if ($result->success) {
            (new ConnectionRepository())->cache_view($view, $result->data);
        } else {
            self::record_client_failure($result, $module);
        }
        AuditRepository::record('hosted_view_fetched', $module, $result->success ? 'success' : 'failure', [
            'view' => $view,
            'error_code' => $result->error_code,
        ], $result->request_id);
        self::redirect('connection', $result->success ? $view . '-ready' : 'hosted-read-failed');
    }

    public static function acknowledge_action(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_acknowledge');
        $action_id = strtolower(trim((string) wp_unslash($_POST['action_id'] ?? '')));
        $state = sanitize_key((string) ($_POST['acknowledgement_state'] ?? ''));
        $note = sanitize_textarea_field((string) wp_unslash($_POST['note'] ?? ''));
        $result = ClientFactory::make()->acknowledge_action($action_id, [
            'state' => $state,
            'acknowledged_at' => gmdate('c'),
            'note' => $note,
        ]);
        if (!$result->success) {
            self::record_client_failure($result);
        }
        AuditRepository::record('action_acknowledged', '', $result->success ? 'success' : 'failure', [
            'action_id' => self::redacted_identifier($action_id),
            'state' => $state,
            'error_code' => $result->error_code,
            'local_mutation' => false,
        ], $result->request_id);
        self::redirect('connection', $result->success ? 'acknowledged' : 'acknowledgement-failed');
    }

    public static function clear_data(): void {
        self::authorize();
        check_admin_referer('mds_project_companion_clear');
        $target = sanitize_key((string) ($_POST['target'] ?? ''));
        if ('export' === $target) {
            $data = [
                'schema_version' => 1,
                'exported_at' => gmdate('c'),
                'settings' => Settings::get(),
                'discovery_cache' => get_option(Settings::DISCOVERY_CACHE, []),
                'connection' => (new ConnectionRepository())->state(),
                'audit' => AuditRepository::recent(100),
            ];
            nocache_headers();
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename=mds-project-companion-export.json');
            echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            exit;
        }
        if ('cache' === $target) {
            delete_option(Settings::DISCOVERY_CACHE);
            (new ConnectionRepository())->clear_cached_views();
            AuditRepository::record('hosted_cache_cleared', '', 'success');
        } elseif ('profile' === $target) {
            $settings = Settings::get();
            $settings['profile'] = Settings::defaults()['profile'];
            update_option(Settings::OPTION, $settings, false);
        } elseif ('audit' === $target) {
            AuditRepository::purge();
        } else {
            self::redirect('shared-data', 'clear-failed');
        }
        AuditRepository::record('data_cleared', '', 'success', ['target' => $target]);
        self::redirect('shared-data', 'cleared');
    }

    private static function authorize(): void {
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('You are not allowed to manage Project Companion.', 'mds-project-companion'),
                esc_html__('Permission denied', 'mds-project-companion'),
                ['response' => 403]
            );
        }
    }

    private static function request_tab(): string {
        return sanitize_key((string) ($_POST['return_tab'] ?? 'overview'));
    }

    private static function redirect(string $tab, string $status): void {
        wp_safe_redirect(add_query_arg([
            'page' => self::PAGE,
            'tab' => sanitize_key($tab),
            'status' => sanitize_key($status),
        ], admin_url('admin.php')));
        exit;
    }

    private static function redacted_identifier(string $identifier): string {
        if (strlen($identifier) <= 8) {
            return '[redacted]';
        }
        return substr($identifier, 0, 4) . '…' . substr($identifier, -4);
    }

    private static function record_client_failure(ClientResult $result, string $module = ''): void {
        $response_errors = ['invalid_json', 'invalid_schema', 'response_too_large', 'invalid_error_response'];
        $event = in_array($result->error_code, $response_errors, true)
            ? 'response_rejected'
            : 'remote_request_failed';
        AuditRepository::record($event, $module, 'failure', ['error_code' => $result->error_code], $result->request_id);
    }
}
