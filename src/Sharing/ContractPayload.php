<?php
/**
 * Exact consent-bound payload construction for connector contract v1.0.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Sharing;

use MillionDollarScript\Extensions\ProjectCompanion\Platform\ConnectorContract;

if (!defined('ABSPATH')) {
    exit;
}

final class ContractPayload {
    /**
     * @param array<int,array<string,mixed>> $fields
     * @param array<string,mixed>|null       $adapter
     */
    public static function preview_hash(string $kind, array $fields, ?array $adapter = null): string {
        usort($fields, static fn(array $left, array $right): int => strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? '')));
        $canonical = [
            'kind' => $kind,
            'schema' => ConnectorContract::SCHEMA_VERSION,
        ];
        if (null !== $adapter) {
            $canonical['adapter'] = $adapter;
        }
        $canonical['fields'] = $fields;
        return hash('sha256', (string) wp_json_encode($canonical, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<int,array<string,mixed>> $metrics
     */
    public static function metric_preview_hash(array $metrics): string {
        usort($metrics, static fn(array $left, array $right): int => strcmp((string) ($left['field_id'] ?? ''), (string) ($right['field_id'] ?? '')));
        return hash('sha256', (string) wp_json_encode($metrics, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param array<string,mixed>            $adapter
     * @param array<int,array<string,mixed>> $fields
     */
    public static function draft_preview_hash(string $kind, array $adapter, array $fields): string {
        usort($fields, static fn(array $left, array $right): int => strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? '')));
        return hash('sha256', (string) wp_json_encode([
            'kind' => $kind,
            'adapter' => $adapter,
            'fields' => $fields,
        ], JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string,mixed>
     */
    public static function project_adapter(string $source_id = 'site-project'): array {
        return [
            'adapter_key' => 'generic-project-context',
            'public_contract' => 'mds.public.project-context.v1',
            'schema_version' => ConnectorContract::SCHEMA_VERSION,
            'source_object_type' => 'project',
            'source_object_id' => mb_substr(sanitize_text_field($source_id), 0, 200),
        ];
    }

    /**
     * @param array<string,mixed> $preview
     * @return array<string,mixed>
     */
    public static function approved(array $preview): array {
        $payload = [
            'schema_version' => ConnectorContract::SCHEMA_VERSION,
            'preview_hash' => (string) ($preview['hash'] ?? ''),
            'selected_field_ids' => array_values((array) ($preview['field_ids'] ?? [])),
            'consented_at' => gmdate('c'),
            'retention_class' => 'project_lifecycle',
        ];
        if ('project_context' === ($preview['transfer_kind'] ?? '')) {
            $payload['adapter'] = (array) ($preview['adapter'] ?? []);
            $payload['fields'] = array_values((array) ($preview['fields'] ?? []));
        } elseif ('capability_snapshot' === ($preview['transfer_kind'] ?? '')) {
            $payload['snapshot'] = (array) ($preview['snapshot'] ?? []);
        }
        return $payload;
    }

    /**
     * Convert the normalized runtime discovery snapshot to the connector schema.
     *
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    public static function capability_snapshot(array $snapshot): array {
        $extensions = [];
        foreach (array_slice((array) ($snapshot['extensions'] ?? []), 0, 500) as $extension) {
            if (!is_array($extension) || empty($extension['slug'])) {
                continue;
            }
            $capabilities = array_values(array_unique(array_merge(
                (array) ($extension['provides'] ?? []),
                (array) ($extension['registration_capabilities'] ?? [])
            )));
            sort($capabilities);
            $item = [
                'key' => sanitize_key((string) $extension['slug']),
                'version' => sanitize_text_field((string) ($extension['version'] ?? '')),
                'state' => !empty($extension['active'])
                    ? 'active'
                    : (!empty($extension['installed']) ? 'inactive' : 'unavailable'),
                'capabilities' => $capabilities,
            ];
            foreach (['requires' => 'dependencies', 'conflicts' => 'conflicts'] as $source => $target) {
                $values = array_values(array_map('sanitize_text_field', (array) ($extension[$source] ?? [])));
                if ($values) {
                    $item[$target] = $values;
                }
            }
            if (!empty($extension['requires_service'])) {
                $item['service_requirements'] = ['external_service'];
            }
            $extensions[] = $item;
        }
        $capabilities = array_values(array_map('sanitize_text_field', (array) ($snapshot['active_capabilities'] ?? [])));
        sort($capabilities);
        return [
            'core_api_version' => sanitize_text_field((string) ($snapshot['core']['version'] ?? '')),
            'extension_api_version' => sanitize_text_field((string) ($snapshot['core']['extension_api_version'] ?? '')),
            'extensions' => $extensions,
            'public_capabilities' => $capabilities,
        ];
    }

    /**
     * Represent a snapshot as fields for the platform-owned canonical preview algorithm.
     *
     * @param array<string,mixed> $snapshot
     * @return array<int,array<string,mixed>>
     */
    public static function capability_fields(array $snapshot): array {
        $purposes = [
            'core_api_version' => 'Match hosted guidance to the installed Million Dollar Script version',
            'extension_api_version' => 'Negotiate the public extension API schema',
            'extensions' => 'Describe administrator-approved extension runtime state',
            'public_capabilities' => 'Describe active public capabilities',
        ];
        $fields = [];
        foreach ($purposes as $id => $purpose) {
            $fields[] = [
                'id' => $id,
                'source_module' => 'companion',
                'purpose' => $purpose,
                'classification' => 'internal',
                'value' => $snapshot[$id],
            ];
        }
        return $fields;
    }
}
