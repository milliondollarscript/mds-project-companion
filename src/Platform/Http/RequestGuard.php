<?php
/**
 * Fail-closed validation for connector write payloads.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform\Http;

use MillionDollarScript\Extensions\ProjectCompanion\Sharing\ContractPayload;

if (!defined('ABSPATH')) {
    exit;
}

final class RequestGuard {
    private const FIELD_PATTERN = '/^[a-z][a-z0-9_.-]{0,119}$/';
    private const MAX_WRITE_BYTES = 262144;

    /**
     * @param array<string,mixed> $payload
     */
    public static function transfer(string $kind, array $payload): bool {
        $specific = 'capability_snapshot' === $kind ? ['snapshot'] : ['adapter', 'fields'];
        if (!self::exact($payload, array_merge(self::consent_keys(), $specific)) || !self::consent($payload)) {
            return false;
        }
        if ('capability_snapshot' === $kind) {
            $snapshot = $payload['snapshot'];
            if (
                !is_array($snapshot)
                || !self::exact($snapshot, ['core_api_version', 'extension_api_version', 'extensions', 'public_capabilities'])
                || !is_array($snapshot['extensions'])
                || !is_array($snapshot['public_capabilities'])
                || !is_string($snapshot['core_api_version'])
                || !is_string($snapshot['extension_api_version'])
                || !self::extension_capabilities($snapshot['extensions'])
                || !self::string_list($snapshot['public_capabilities'])
                || count($snapshot['public_capabilities']) > 500
                || !self::bounded($payload)
            ) {
                return false;
            }
            $fields = ContractPayload::capability_fields($snapshot);
            return self::bound(
                $payload,
                $fields,
                ContractPayload::preview_hash('capability_snapshot', $fields)
            );
        }
        if (
            !self::adapter($payload['adapter'])
            || !self::fields($payload['fields'], 200)
            || !self::allowed_field_ids($payload['fields'], [
                'project.name', 'project.summary', 'project.type', 'project.goals',
                'project.constraints', 'project.resources',
            ])
            || !self::bounded($payload)
        ) {
            return false;
        }
        return self::bound(
            $payload,
            $payload['fields'],
            ContractPayload::preview_hash('project_context', $payload['fields'], $payload['adapter'])
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function draft(array $payload): bool {
        if (!(
            self::exact($payload, array_merge(self::consent_keys(), ['draft_kind', 'adapter', 'fields']))
            && self::consent($payload)
            && 'project_lifecycle' === $payload['retention_class']
            && in_array($payload['draft_kind'], ['product', 'offer'], true)
            && self::adapter($payload['adapter'])
            && self::fields($payload['fields'], 100)
            && self::bounded($payload)
        )) {
            return false;
        }
        return self::bound(
            $payload,
            $payload['fields'],
            ContractPayload::draft_preview_hash($payload['draft_kind'], $payload['adapter'], $payload['fields'])
        );
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function metrics(array $payload): bool {
        if (
            !self::exact($payload, array_merge(self::consent_keys(), ['metrics']))
            || !self::consent($payload)
            || 'project_lifecycle' !== $payload['retention_class']
            || !is_array($payload['metrics'])
            || !$payload['metrics']
            || count($payload['metrics']) > 200
        ) {
            return false;
        }
        foreach ($payload['metrics'] as $metric) {
            if (
                !is_array($metric)
                || !self::exact($metric, [
                    'field_id', 'metric_key', 'value', 'unit', 'period_start', 'period_end',
                    'attribution', 'aggregation_level', 'classification',
                ], ['currency'])
                || 'non_personal_aggregate' !== $metric['classification']
                || !is_int($metric['value'])
                || !preg_match(self::FIELD_PATTERN, (string) $metric['field_id'])
                || preg_match('/customer|order|payment|message|private[._-]?sponsor|personal|email|phone|address|ip[._-]?address/i', (string) $metric['field_id'])
                || !preg_match(self::FIELD_PATTERN, (string) $metric['metric_key'])
                || !in_array($metric['unit'], ['count', 'money_minor', 'basis_points', 'minutes', 'score'], true)
                || !in_array($metric['aggregation_level'], ['site', 'project', 'campaign'], true)
                || false === strtotime((string) $metric['period_start'])
                || false === strtotime((string) $metric['period_end'])
                || strtotime((string) $metric['period_end']) <= strtotime((string) $metric['period_start'])
                || ('money_minor' === $metric['unit'] && !preg_match('/^[A-Z]{3}$/', (string) ($metric['currency'] ?? '')))
                || ('money_minor' !== $metric['unit'] && array_key_exists('currency', $metric))
            ) {
                return false;
            }
        }
        if (!self::bounded($payload)) {
            return false;
        }
        $ids = array_map(static fn(array $metric): string => (string) $metric['field_id'], $payload['metrics']);
        sort($ids);
        $selected = array_values($payload['selected_field_ids']);
        return $ids === $selected
            && hash_equals(
                (string) $payload['preview_hash'],
                ContractPayload::metric_preview_hash($payload['metrics'])
            );
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function consent(array $payload): bool {
        $ids = $payload['selected_field_ids'] ?? null;
        if (
            1 !== (int) ($payload['schema_version'] ?? 0)
            || !is_string($payload['preview_hash'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/', $payload['preview_hash'])
            || !is_array($ids)
            || !$ids
            || count($ids) !== count(array_unique($ids))
            || false === strtotime((string) ($payload['consented_at'] ?? ''))
            || !in_array($payload['retention_class'] ?? '', ['connection', 'project_lifecycle', 'security_audit'], true)
        ) {
            return false;
        }
        foreach ($ids as $id) {
            if (!is_string($id) || !preg_match(self::FIELD_PATTERN, $id)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $adapter
     */
    private static function adapter($adapter): bool {
        return is_array($adapter)
            && self::exact($adapter, ['adapter_key', 'public_contract', 'schema_version', 'source_object_type'], ['source_object_id'])
            && preg_match('/^[a-z0-9][a-z0-9._-]{0,119}$/', (string) $adapter['adapter_key'])
            && strlen((string) $adapter['public_contract']) >= 1
            && strlen((string) $adapter['public_contract']) <= 200
            && 1 <= (int) $adapter['schema_version']
            && strlen((string) $adapter['source_object_type']) >= 1
            && strlen((string) $adapter['source_object_type']) <= 120
            && strlen((string) ($adapter['source_object_id'] ?? '')) <= 200;
    }

    /**
     * @param mixed $fields
     */
    private static function fields($fields, int $maximum): bool {
        if (!is_array($fields) || !$fields || count($fields) > $maximum) {
            return false;
        }
        $ids = [];
        foreach ($fields as $field) {
            if (
                !is_array($field)
                || !self::exact($field, ['id', 'source_module', 'purpose', 'classification', 'value'])
                || !preg_match(self::FIELD_PATTERN, (string) $field['id'])
                || !in_array($field['classification'], ['public', 'internal'], true)
                || '' === (string) $field['source_module']
                || strlen((string) $field['source_module']) > 120
                || '' === (string) $field['purpose']
                || strlen((string) $field['purpose']) > 240
                || preg_match('/customer|order|payment|message|private[._-]?sponsor|personal|email|phone|address|ip[._-]?address/i', (string) $field['id'])
            ) {
                return false;
            }
            $ids[] = $field['id'];
        }
        return count($ids) === count(array_unique($ids));
    }

    /**
     * @param array<string,mixed>            $payload
     * @param array<int,array<string,mixed>> $fields
     */
    private static function bound(array $payload, array $fields, string $hash): bool {
        $ids = array_map(static fn(array $field): string => (string) $field['id'], $fields);
        sort($ids);
        return $ids === array_values($payload['selected_field_ids'])
            && hash_equals((string) $payload['preview_hash'], $hash);
    }

    /**
     * @param array<int,array<string,mixed>> $fields
     * @param array<int,string>              $allowed
     */
    private static function allowed_field_ids(array $fields, array $allowed): bool {
        foreach ($fields as $field) {
            if (!in_array($field['id'], $allowed, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $extensions
     */
    private static function extension_capabilities($extensions): bool {
        if (!is_array($extensions) || count($extensions) > 500) {
            return false;
        }
        foreach ($extensions as $extension) {
            if (
                !is_array($extension)
                || !self::exact($extension, ['key', 'version', 'state', 'capabilities'], ['dependencies', 'conflicts', 'service_requirements'])
                || !preg_match('/^[a-z0-9][a-z0-9._-]{0,119}$/', (string) $extension['key'])
                || !is_string($extension['version'])
                || !in_array($extension['state'], ['active', 'inactive', 'unavailable'], true)
                || !self::string_list($extension['capabilities'])
                || count($extension['capabilities']) > 200
            ) {
                return false;
            }
            foreach (['dependencies', 'conflicts', 'service_requirements'] as $optional) {
                if (
                    isset($extension[$optional])
                    && (!self::string_list($extension[$optional]) || count($extension[$optional]) > 100)
                ) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     */
    private static function string_list($value): bool {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function bounded(array $payload): bool {
        $encoded = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        return is_string($encoded) && strlen($encoded) <= self::MAX_WRITE_BYTES;
    }

    /**
     * @return array<int,string>
     */
    private static function consent_keys(): array {
        return ['schema_version', 'preview_hash', 'selected_field_ids', 'consented_at', 'retention_class'];
    }

    /**
     * @param array<string,mixed> $value
     * @param array<int,string> $required
     * @param array<int,string> $optional
     */
    private static function exact(array $value, array $required, array $optional = []): bool {
        foreach ($required as $key) {
            if (!array_key_exists($key, $value)) {
                return false;
            }
        }
        return !array_diff(array_keys($value), array_merge($required, $optional));
    }
}
