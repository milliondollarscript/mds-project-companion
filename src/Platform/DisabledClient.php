<?php
/**
 * Safe client when no valid hosted API origin is configured.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform;

if (!defined('ABSPATH')) {
    exit;
}

final class DisabledClient implements PlatformClient {
    public function discover_capabilities(): ClientResult { return $this->disabled(); }
    public function begin_pairing(array $context = []): ClientResult { return $this->disabled(); }
    public function complete_pairing(array $context = []): ClientResult { return $this->disabled(); }
    public function get_installation(): ClientResult { return $this->disabled(); }
    public function test_connection(): ClientResult { return $this->disabled(); }
    public function rotate_credential(): ClientResult { return $this->disabled(); }
    public function revoke_connection(array $context = []): ClientResult { return $this->disabled(); }
    public function send_capability_snapshot(array $snapshot): ClientResult { return $this->disabled(); }
    public function send_project_context(array $context): ClientResult { return $this->disabled(); }
    public function fetch_plan(): ClientResult { return $this->disabled(); }
    public function fetch_create(): ClientResult { return $this->disabled(); }
    public function create_draft(string $type, array $payload): ClientResult { return $this->disabled(); }
    public function fetch_distribution(): ClientResult { return $this->disabled(); }
    public function send_aggregate_metrics(array $metrics): ClientResult { return $this->disabled(); }
    public function fetch_growth(): ClientResult { return $this->disabled(); }
    public function fetch_project_summary(): ClientResult { return $this->disabled(); }
    public function fetch_recommendations(array $context = []): ClientResult { return $this->disabled(); }
    public function acknowledge_action(string $action_id, array $context = []): ClientResult { return $this->disabled(); }

    private function disabled(): ClientResult {
        return new ClientResult(
            false,
            'disabled',
            '',
            [],
            'hosted_api_not_configured',
            __('Hosted connection features are unavailable until a valid API origin is configured.', 'mds-project-companion')
        );
    }
}
