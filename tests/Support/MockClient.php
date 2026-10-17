<?php
/**
 * Deterministic development-only platform client.
 *
 * This file is excluded from distributable packages.
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Tests\Support;

use MillionDollarScript\Extensions\ProjectCompanion\Platform\ClientResult;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\PlatformClient;

final class MockClient implements PlatformClient {
    public function __construct(private string $scenario = 'success') {}
    public function discover_capabilities(): ClientResult { return $this->result('compatible_contract_discovered'); }
    public function begin_pairing(array $context = []): ClientResult { return $this->result('pairing_started'); }
    public function complete_pairing(array $context = []): ClientResult { return $this->result('connected'); }
    public function get_installation(): ClientResult { return $this->result('connected'); }
    public function test_connection(): ClientResult { return $this->result('connected'); }
    public function rotate_credential(): ClientResult { return $this->result('credential_rotated'); }
    public function revoke_connection(array $context = []): ClientResult { return $this->result('revoked'); }
    public function send_capability_snapshot(array $snapshot): ClientResult { return $this->result('accepted'); }
    public function send_project_context(array $context): ClientResult { return $this->result('accepted'); }
    public function fetch_plan(): ClientResult { return $this->result('plan_ready'); }
    public function fetch_create(): ClientResult { return $this->result('create_ready'); }
    public function create_draft(string $type, array $payload): ClientResult { return $this->result('draft_created'); }
    public function fetch_distribution(): ClientResult { return $this->result('distribution_ready'); }
    public function send_aggregate_metrics(array $metrics): ClientResult { return $this->result('accepted'); }
    public function fetch_growth(): ClientResult { return $this->result('growth_ready'); }
    public function fetch_project_summary(): ClientResult { return $this->result('summary_ready'); }
    public function fetch_recommendations(array $context = []): ClientResult { return $this->result('recommendations_ready'); }
    public function acknowledge_action(string $action_id, array $context = []): ClientResult { return $this->result('acknowledged'); }

    private function result(string $success_status): ClientResult {
        if ('success' === $this->scenario) {
            return new ClientResult(true, $success_status, 'mock-request-0001', ['fixture' => true]);
        }
        $codes = [
            'timeout' => 'request_timeout',
            'invalid-json' => 'invalid_json',
            'oversized' => 'response_too_large',
            'schema-mismatch' => 'unsupported_schema',
            'revoked' => 'credential_revoked',
            'remote-error' => 'remote_failure',
        ];
        return new ClientResult(false, 'failed', 'mock-request-0001', [], $codes[$this->scenario] ?? 'mock_failure', 'Deterministic mock failure.');
    }
}
