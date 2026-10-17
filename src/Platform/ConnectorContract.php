<?php
/**
 * Runtime constants mirrored from the platform-owned connector contract.
 *
 * Source: unified-project-platform commit 76ae97c.
 * Contract: api/connector-openapi.yaml and api/fixtures/connector-v1/compatibility.json.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform;

if (!defined('ABSPATH')) {
    exit;
}

final class ConnectorContract {
    public const SOURCE_COMMIT = '76ae97c';
    public const OPENAPI_SHA256 = '137614d1a7487bc9235d48b9ec788091fa3aa8b965507b8458d46163b664d9ce';
    public const FIXTURE_SHA256 = '42bc22521ed7b2d3af208fce6516442bda0a34aa21aff134ac9ffe7b69cc2e8e';
    public const API_MAJOR = 'v1';
    public const SERVER_PATH = '/api/v1';
    public const VERSION = '1.0';
    public const SCHEMA_VERSION = 1;

    public const PATHS = [
        'capabilities' => '/connector/capabilities',
        'begin_pairing' => '/connector/pairings',
        'complete_pairing' => '/connector/pairings/{pairingId}/complete',
        'installation' => '/connector/installation',
        'test_connection' => '/connector/installation/test',
        'rotate_credential' => '/connector/installation/credentials/rotate',
        'revoke_connection' => '/connector/installation/revoke',
        'capability_snapshot' => '/connector/snapshots',
        'project_context' => '/connector/project-context',
        'plan' => '/connector/plan',
        'recommendations' => '/connector/recommendations',
        'create' => '/connector/create',
        'draft' => '/connector/drafts',
        'distribution' => '/connector/distribution',
        'metrics' => '/connector/metrics',
        'growth' => '/connector/growth',
        'acknowledgement' => '/connector/actions/{actionId}/acknowledge',
    ];

    public const SCHEMAS = [
        'capability_snapshot' => self::SCHEMA_VERSION,
        'project_context' => self::SCHEMA_VERSION,
        'product_draft' => self::SCHEMA_VERSION,
        'offer_draft' => self::SCHEMA_VERSION,
        'aggregate_metrics' => self::SCHEMA_VERSION,
        'action_acknowledgement' => self::SCHEMA_VERSION,
    ];

    public const SCOPES = [
        'installation_read' => 'connector:installation:read',
        'snapshot_write' => 'connector:snapshot:write',
        'context_write' => 'connector:context:write',
        'plan_read' => 'connector:plan:read',
        'create_read' => 'connector:create:read',
        'draft_write' => 'connector:drafts:write',
        'distribution_read' => 'connector:distribution:read',
        'metrics_write' => 'connector:metrics:write',
        'growth_read' => 'connector:growth:read',
        'action_acknowledge' => 'connector:actions:acknowledge',
        'credential_rotate' => 'connector:credentials:rotate',
        'installation_revoke' => 'connector:installation:revoke',
    ];

    public const DEFERRED_CAPABILITIES = [
        'webhooks',
        'scheduled_sync',
        'publishing',
        'repository_access',
        'codex_access',
        'personal_analytics',
        'bulk_sync',
        'automatic_local_actions',
    ];

    /**
     * Return the least-privilege scope set for the enabled modules.
     *
     * @param array<string,bool> $modules
     * @param array<string,bool> $available_writes
     * @return array<int,string>
     */
    public static function scopes_for_modules(array $modules, array $available_writes = []): array {
        $scopes = [
            self::SCOPES['installation_read'],
            self::SCOPES['credential_rotate'],
            self::SCOPES['installation_revoke'],
        ];
        if (!empty($modules['plan'])) {
            $scopes[] = self::SCOPES['context_write'];
            $scopes[] = self::SCOPES['plan_read'];
        }
        if (!empty($modules['create'])) {
            $scopes[] = self::SCOPES['snapshot_write'];
            $scopes[] = self::SCOPES['create_read'];
        }
        if (!empty($modules['distribute'])) {
            $scopes[] = self::SCOPES['distribution_read'];
            if (!empty($available_writes['draft'])) {
                $scopes[] = self::SCOPES['draft_write'];
            }
        }
        if (!empty($modules['grow'])) {
            $scopes[] = self::SCOPES['growth_read'];
            if (!empty($available_writes['metrics'])) {
                $scopes[] = self::SCOPES['metrics_write'];
            }
        }
        if (array_filter($modules)) {
            $scopes[] = self::SCOPES['action_acknowledge'];
        }
        return array_values(array_unique($scopes));
    }
}
