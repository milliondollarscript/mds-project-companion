<?php
/**
 * Brand-neutral hosted-platform operation boundary.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform;

if (!defined('ABSPATH')) {
    exit;
}

interface PlatformClient {
    public function discover_capabilities(): ClientResult;
    public function begin_pairing(array $context = []): ClientResult;
    public function complete_pairing(array $context = []): ClientResult;
    public function get_installation(): ClientResult;
    public function test_connection(): ClientResult;
    public function rotate_credential(): ClientResult;
    public function revoke_connection(array $context = []): ClientResult;
    public function send_capability_snapshot(array $snapshot): ClientResult;
    public function send_project_context(array $context): ClientResult;
    public function fetch_plan(): ClientResult;
    public function fetch_create(): ClientResult;
    public function create_draft(string $type, array $payload): ClientResult;
    public function fetch_distribution(): ClientResult;
    public function send_aggregate_metrics(array $metrics): ClientResult;
    public function fetch_growth(): ClientResult;
    public function fetch_project_summary(): ClientResult;
    public function fetch_recommendations(array $context = []): ClientResult;
    public function acknowledge_action(string $action_id, array $context = []): ClientResult;
}
