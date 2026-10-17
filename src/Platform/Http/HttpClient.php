<?php
/**
 * Connector contract v1.0 HTTP client.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform\Http;

use MillionDollarScript\Extensions\ProjectCompanion\Platform\ClientResult;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\ConnectionRepository;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\ConnectorContract;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\PlatformClient;

if (!defined('ABSPATH')) {
    exit;
}

final class HttpClient implements PlatformClient {
    /**
     * @param array<string,mixed> $config
     */
    public function __construct(
        private array $config,
        private ConnectionRepository $connections,
        private Transport $transport
    ) {
    }

    public function discover_capabilities(): ClientResult {
        return $this->request('GET', 'capabilities', [], '', '', [200], 'capability_discovery', true);
    }

    public function begin_pairing(array $context = []): ClientResult {
        $token = trim((string) ($context['platform_token'] ?? ''));
        $payload = is_array($context['payload'] ?? null) ? $context['payload'] : [];
        if ('' === $token) {
            return $this->local_error('missing_platform_token', __('A temporary authenticated platform token is required to begin pairing.', 'mds-project-companion'));
        }
        return $this->request('POST', 'begin_pairing', $payload, $token, 'platform', [201], 'pairing_challenge', true);
    }

    public function complete_pairing(array $context = []): ClientResult {
        $pairing_id = (string) ($context['pairing_id'] ?? '');
        $secret = trim((string) ($context['pairing_secret'] ?? ''));
        $payload = is_array($context['payload'] ?? null) ? $context['payload'] : [];
        if (!self::uuid($pairing_id) || '' === $secret) {
            return $this->local_error('invalid_pairing_state', __('The pairing challenge is missing or expired.', 'mds-project-companion'));
        }
        $result = $this->request(
            'POST',
            'complete_pairing',
            $payload,
            $secret,
            'pairing',
            [200],
            'pairing_result',
            true,
            ['pairingId' => $pairing_id]
        );
        if (
            $result->success
            && (
                !is_array($result->data['installation'] ?? null)
                || !$this->negotiated($result->data['installation'])
                || array_diff(
                    array_values((array) ($context['required_scopes'] ?? [])),
                    array_values((array) ($result->data['installation']['scopes'] ?? []))
                )
            )
        ) {
            return $this->local_error('schema_mismatch', __('The completed pairing did not match the requested contract, schemas, or scopes.', 'mds-project-companion'));
        }
        return $result;
    }

    public function get_installation(): ClientResult {
        $result = $this->credential_request('GET', 'installation', [], [200], 'installation', true);
        if ($result->success && !$this->negotiated($result->data)) {
            $this->connections->fail('schema_mismatch', true);
            return $this->local_error('schema_mismatch', __('The connected installation uses an unsupported connector contract.', 'mds-project-companion'));
        }
        return $result;
    }

    public function test_connection(): ClientResult {
        $result = $this->credential_request('POST', 'test_connection', [], [200], 'connection_test', true);
        if ($result->success && !$this->negotiated($result->data)) {
            $this->connections->fail('schema_mismatch', true);
            return $this->local_error('schema_mismatch', __('The connected installation uses an unsupported connector contract.', 'mds-project-companion'));
        }
        return $result;
    }

    public function rotate_credential(): ClientResult {
        return $this->credential_request('POST', 'rotate_credential', [], [200], 'credential_result', false);
    }

    public function revoke_connection(array $context = []): ClientResult {
        $reason = (string) ($context['reason'] ?? 'administrator_disconnected');
        if (!in_array($reason, ['administrator_disconnected', 'site_disconnected', 'compromised', 'replaced'], true)) {
            return $this->local_error('invalid_revocation_reason', __('The revocation reason is not supported by the connector contract.', 'mds-project-companion'));
        }
        return $this->credential_request('POST', 'revoke_connection', ['reason' => $reason], [200], 'revocation_result', true);
    }

    public function send_capability_snapshot(array $snapshot): ClientResult {
        if (!RequestGuard::transfer('capability_snapshot', $snapshot)) {
            return $this->local_error('invalid_transfer_payload', __('The capability snapshot did not match the approved connector schema.', 'mds-project-companion'));
        }
        return $this->credential_request('POST', 'capability_snapshot', $snapshot, [200], 'transfer_result', true);
    }

    public function send_project_context(array $context): ClientResult {
        if (!RequestGuard::transfer('project_context', $context)) {
            return $this->local_error('invalid_transfer_payload', __('The project context did not match the approved connector schema.', 'mds-project-companion'));
        }
        return $this->credential_request('POST', 'project_context', $context, [200], 'transfer_result', true);
    }

    public function fetch_plan(): ClientResult {
        return $this->credential_request('GET', 'plan', [], [200], 'plan_view', true);
    }

    public function fetch_project_summary(): ClientResult {
        return $this->fetch_plan();
    }

    public function fetch_recommendations(array $context = []): ClientResult {
        return $this->credential_request('GET', 'recommendations', [], [200], 'recommendation_view', true);
    }

    public function fetch_create(): ClientResult {
        return $this->credential_request('GET', 'create', [], [200], 'create_view', true);
    }

    public function create_draft(string $type, array $payload): ClientResult {
        if (
            !in_array($type, ['product', 'offer'], true)
            || ($payload['draft_kind'] ?? '') !== $type
            || !RequestGuard::draft($payload)
        ) {
            return $this->local_error('invalid_draft_kind', __('Only product and offer draft requests are supported.', 'mds-project-companion'));
        }
        return $this->credential_request('POST', 'draft', $payload, [201], 'draft_result', true);
    }

    public function fetch_distribution(): ClientResult {
        return $this->credential_request('GET', 'distribution', [], [200], 'distribution_view', true);
    }

    public function send_aggregate_metrics(array $metrics): ClientResult {
        if (!RequestGuard::metrics($metrics)) {
            return $this->local_error('invalid_metric_payload', __('The metrics did not match the approved non-personal aggregate schema.', 'mds-project-companion'));
        }
        return $this->credential_request('POST', 'metrics', $metrics, [200], 'transfer_result', true);
    }

    public function fetch_growth(): ClientResult {
        return $this->credential_request('GET', 'growth', [], [200], 'growth_view', true);
    }

    public function acknowledge_action(string $action_id, array $context = []): ClientResult {
        if (!self::uuid($action_id)) {
            return $this->local_error('invalid_action_id', __('The hosted action identifier is invalid.', 'mds-project-companion'));
        }
        $state = (string) ($context['state'] ?? '');
        if (!in_array($state, ['seen', 'accepted_for_manual_review', 'dismissed'], true)) {
            return $this->local_error('invalid_acknowledgement_state', __('The acknowledgement state is invalid.', 'mds-project-companion'));
        }
        $payload = [
            'state' => $state,
            'acknowledged_at' => (string) ($context['acknowledged_at'] ?? gmdate('c')),
        ];
        $note = mb_substr(sanitize_textarea_field((string) ($context['note'] ?? '')), 0, 500);
        if ('' !== $note) {
            $payload['note'] = $note;
        }
        return $this->credential_request(
            'POST',
            'acknowledgement',
            $payload,
            [200],
            'acknowledgement_result',
            true,
            ['actionId' => $action_id]
        );
    }

    /**
     * @param array<string,mixed> $body
     * @param array<int,int>      $expected_statuses
     * @param array<string,string> $path_parameters
     */
    private function credential_request(
        string $method,
        string $operation,
        array $body,
        array $expected_statuses,
        string $schema,
        bool $retryable,
        array $path_parameters = []
    ): ClientResult {
        $credential = $this->connections->credential();
        if ('' === $credential) {
            return $this->local_error('connector_credential_missing', __('Connect this site before using hosted features.', 'mds-project-companion'));
        }
        return $this->request(
            $method,
            $operation,
            $body,
            $credential,
            'connector',
            $expected_statuses,
            $schema,
            $retryable,
            $path_parameters
        );
    }

    /**
     * @param array<string,mixed>  $body
     * @param array<int,int>       $expected_statuses
     * @param array<string,string> $path_parameters
     */
    private function request(
        string $method,
        string $operation,
        array $body,
        string $token,
        string $auth_class,
        array $expected_statuses,
        string $schema,
        bool $retryable,
        array $path_parameters = []
    ): ClientResult {
        $path = ConnectorContract::PATHS[$operation] ?? '';
        foreach ($path_parameters as $name => $value) {
            $path = str_replace('{' . $name . '}', rawurlencode($value), $path);
        }
        if ('' === $path || str_contains($path, '{')) {
            return $this->local_error('invalid_contract_path', __('The connector operation is unavailable.', 'mds-project-companion'));
        }
        $url = rtrim((string) ($this->config['api_origin'] ?? ''), '/')
            . ConnectorContract::SERVER_PATH
            . $path;
        $headers = ['Accept' => 'application/json, application/problem+json'];
        if ('' !== $token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        if ('GET' !== $method) {
            $headers['Content-Type'] = 'application/json';
            $headers['Idempotency-Key'] = $this->idempotency_key($operation);
        }
        $arguments = ['method' => $method, 'headers' => $headers];
        if ('GET' !== $method && $body) {
            $arguments['body'] = wp_json_encode($body, JSON_UNESCAPED_SLASHES);
        }

        $attempt = 0;
        do {
            $response = $this->transport->request($url, $arguments);
            if (is_wp_error($response)) {
                if ($retryable && $attempt < EndpointPolicy::MAX_RETRIES) {
                    ++$attempt;
                    continue;
                }
                return $this->local_error(
                    'request_failed',
                    __('The hosted request could not be completed.', 'mds-project-companion')
                );
            }

            $status = (int) wp_remote_retrieve_response_code($response);
            $response_body = (string) wp_remote_retrieve_body($response);
            if (in_array($status, $expected_statuses, true)) {
                $decoded = ResponseGuard::decode($response_body, $schema);
                if (is_wp_error($decoded)) {
                    return $this->local_error($decoded->get_error_code(), $decoded->get_error_message());
                }
                return new ClientResult(
                    true,
                    $this->success_status($operation, $decoded),
                    (string) ($decoded['request_id'] ?? ''),
                    $decoded
                );
            }

            $problem = ResponseGuard::decode($response_body, 'problem');
            if (is_wp_error($problem)) {
                return $this->local_error('invalid_error_response', __('The hosted service returned an invalid error response.', 'mds-project-companion'));
            }
            if (
                $retryable
                && $attempt < EndpointPolicy::MAX_RETRIES
                && !empty($problem['retryable'])
                && in_array($status, [429, 503], true)
            ) {
                ++$attempt;
                continue;
            }
            $code = (string) $problem['code'];
            if (
                'connector' === $auth_class
                && (410 === $status || in_array($code, ['installation_revoked', 'schema_mismatch'], true))
            ) {
                $this->connections->fail($code, true);
            }
            return new ClientResult(
                false,
                'failed',
                (string) $problem['request_id'],
                [],
                $code,
                sanitize_text_field((string) $problem['detail'])
            );
        } while ($attempt <= EndpointPolicy::MAX_RETRIES);

        return $this->local_error('request_failed', __('The hosted request could not be completed.', 'mds-project-companion'));
    }

    /**
     * @param array<string,mixed> $data
     */
    private function success_status(string $operation, array $data): string {
        $statuses = [
            'capabilities' => 'compatible_contract_discovered',
            'begin_pairing' => 'pairing_started',
            'complete_pairing' => 'connected',
            'installation' => (string) ($data['state'] ?? 'connected'),
            'test_connection' => 'connected',
            'rotate_credential' => 'credential_rotated',
            'revoke_connection' => 'revoked',
            'capability_snapshot' => 'accepted',
            'project_context' => 'accepted',
            'plan' => 'plan_ready',
            'recommendations' => 'recommendations_ready',
            'create' => 'create_ready',
            'draft' => 'draft_created',
            'distribution' => 'distribution_ready',
            'metrics' => 'accepted',
            'growth' => 'growth_ready',
            'acknowledgement' => 'acknowledged',
        ];
        return $statuses[$operation] ?? 'success';
    }

    private function idempotency_key(string $operation): string {
        return 'mds-companion-' . sanitize_key($operation) . '-' . bin2hex(random_bytes(16));
    }

    private function local_error(string $code, string $message): ClientResult {
        return new ClientResult(false, 'failed', '', [], sanitize_key($code), $message);
    }

    private static function uuid(string $value): bool {
        return 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    /**
     * @param array<string,mixed> $state
     */
    private function negotiated(array $state): bool {
        if (ConnectorContract::VERSION !== ($state['contract_version'] ?? '')) {
            return false;
        }
        $schemas = (array) ($state['schema_versions'] ?? []);
        foreach (ConnectorContract::SCHEMAS as $name => $version) {
            if ((int) ($schemas[$name] ?? 0) !== $version) {
                return false;
            }
        }
        return true;
    }
}
