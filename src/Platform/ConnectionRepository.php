<?php
/**
 * Extension-owned connector state and credential storage.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform;

if (!defined('ABSPATH')) {
    exit;
}

final class ConnectionRepository {
    public const OPTION = 'mds_project_companion_connection';
    public const SECRET_OPTION = 'mds_project_companion_connector_secret';
    public const PAIRING_PREFIX = 'mds_project_companion_pairing_';

    /**
     * @return array<string,mixed>
     */
    public function state(): array {
        $state = get_option(self::OPTION, []);
        return is_array($state) ? $state : [];
    }

    public function installation_key(): string {
        $state = $this->state();
        $key = (string) ($state['installation_key'] ?? '');
        if (preg_match('/^[A-Za-z0-9._:-]{16,200}$/', $key)) {
            return $key;
        }
        $key = 'mds-companion-' . str_replace('-', '', wp_generate_uuid4());
        $state['installation_key'] = $key;
        $this->store_state($state);
        return $key;
    }

    public function credential(): string {
        $secret = get_option(self::SECRET_OPTION, []);
        $secret = is_array($secret) ? $secret : [];
        return trim((string) ($secret['credential'] ?? ''));
    }

    public function connected(): bool {
        $state = $this->state();
        return 'connected' === ($state['status'] ?? '') && '' !== $this->credential();
    }

    /**
     * @param array<string,mixed> $challenge
     */
    public function store_pairing(int $user_id, array $challenge): void {
        set_transient(
            self::PAIRING_PREFIX . $user_id,
            [
                'pairing_id' => (string) ($challenge['pairing_id'] ?? ''),
                'pairing_secret' => (string) ($challenge['pairing_secret'] ?? ''),
                'user_code' => (string) ($challenge['user_code'] ?? ''),
                'verification_path' => (string) ($challenge['verification_path'] ?? ''),
                'expires_at' => (string) ($challenge['expires_at'] ?? ''),
                'poll_after_seconds' => (int) ($challenge['poll_after_seconds'] ?? 0),
                'requested_contract' => ConnectorContract::VERSION,
                'required_schemas' => ConnectorContract::SCHEMAS,
                'requested_scopes' => array_values((array) ($challenge['requested_scopes'] ?? [])),
            ],
            10 * MINUTE_IN_SECONDS
        );
        $state = $this->state();
        $state['status'] = 'pairing';
        $state['last_error_code'] = '';
        $this->store_state($state);
    }

    /**
     * @return array<string,mixed>
     */
    public function pairing(int $user_id): array {
        $pairing = get_transient(self::PAIRING_PREFIX . $user_id);
        return is_array($pairing) ? $pairing : [];
    }

    public function clear_pairing(int $user_id): void {
        delete_transient(self::PAIRING_PREFIX . $user_id);
    }

    /**
     * @param array<string,mixed> $installation
     * @param array<string,mixed> $credential
     */
    public function connect(array $installation, array $credential): void {
        update_option(self::SECRET_OPTION, ['credential' => (string) ($credential['credential'] ?? '')], false);
        $state = $this->state();
        $state = array_merge($state, [
            'status' => 'connected',
            'installation_id' => (string) ($installation['installation_id'] ?? ''),
            'organization_id' => (string) ($installation['organization_id'] ?? ''),
            'project_id' => (string) ($installation['project_id'] ?? ''),
            'contract_version' => (string) ($installation['contract_version'] ?? ''),
            'schema_versions' => (array) ($installation['schema_versions'] ?? []),
            'scopes' => array_values((array) ($installation['scopes'] ?? [])),
            'connected_at' => (string) ($installation['connected_at'] ?? ''),
            'credential_expires_at' => (string) ($credential['expires_at'] ?? ''),
            'last_tested_at' => '',
            'last_error_code' => '',
        ]);
        $this->store_state($state);
    }

    /**
     * @param array<string,mixed> $test
     */
    public function store_test(array $test): void {
        $state = $this->state();
        $state['status'] = 'connected';
        $state['installation_id'] = (string) ($test['installation_id'] ?? ($state['installation_id'] ?? ''));
        $state['contract_version'] = (string) ($test['contract_version'] ?? ($state['contract_version'] ?? ''));
        $state['schema_versions'] = (array) ($test['schema_versions'] ?? ($state['schema_versions'] ?? []));
        $state['scopes'] = array_values((array) ($test['scopes'] ?? ($state['scopes'] ?? [])));
        $state['credential_expires_at'] = (string) ($test['credential_expires_at'] ?? ($state['credential_expires_at'] ?? ''));
        $state['last_tested_at'] = gmdate('c');
        $state['last_error_code'] = '';
        $this->store_state($state);
    }

    /**
     * @param array<string,mixed> $credential
     */
    public function rotate(array $credential): void {
        update_option(self::SECRET_OPTION, ['credential' => (string) ($credential['credential'] ?? '')], false);
        $state = $this->state();
        $state['credential_expires_at'] = (string) ($credential['expires_at'] ?? '');
        $state['last_error_code'] = '';
        $this->store_state($state);
    }

    public function fail(string $code, bool $clear_credential = false): void {
        if ($clear_credential) {
            delete_option(self::SECRET_OPTION);
        }
        $state = $this->state();
        $state['status'] = $clear_credential ? 'disconnected' : ($state['status'] ?? 'disconnected');
        $state['last_error_code'] = sanitize_key($code);
        $state['last_tested_at'] = gmdate('c');
        if ($clear_credential) {
            unset($state['views']);
        }
        $this->store_state($state);
    }

    public function disconnect(string $status = 'revoked'): void {
        delete_option(self::SECRET_OPTION);
        $state = $this->state();
        $state['status'] = sanitize_key($status);
        $state['credential_expires_at'] = '';
        $state['last_error_code'] = '';
        $state['revoked_at'] = gmdate('c');
        unset($state['views']);
        $this->store_state($state);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function cache_view(string $key, array $data): void {
        $state = $this->state();
        $views = is_array($state['views'] ?? null) ? $state['views'] : [];
        $views[sanitize_key($key)] = ['fetched_at' => gmdate('c'), 'data' => $data];
        $state['views'] = $views;
        $this->store_state($state);
    }

    /**
     * @return array<string,mixed>
     */
    public function view(string $key): array {
        $views = (array) ($this->state()['views'] ?? []);
        $view = $views[sanitize_key($key)] ?? [];
        return is_array($view) ? $view : [];
    }

    public function clear_cached_views(): void {
        $state = $this->state();
        unset($state['views'], $state['compatibility']);
        $this->store_state($state);
    }

    /**
     * @param array<int,string> $field_ids
     */
    public function record_transfer(string $kind, array $field_ids, string $request_id): void {
        $state = $this->state();
        $transfers = is_array($state['last_transfers'] ?? null) ? $state['last_transfers'] : [];
        foreach ($field_ids as $field_id) {
            $transfers[(string) $field_id] = [
                'kind' => sanitize_key($kind),
                'transferred_at' => gmdate('c'),
                'request_id' => mb_substr(sanitize_text_field($request_id), 0, 64),
            ];
        }
        $state['last_transfers'] = $transfers;
        $this->store_state($state);
    }

    /**
     * @param array<string,mixed> $result
     */
    public function store_compatibility(array $result): void {
        $state = $this->state();
        $state['compatibility'] = $result + ['checked_at' => gmdate('c')];
        $this->store_state($state);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function store_state(array $state): void {
        update_option(self::OPTION, $state, false);
    }
}
