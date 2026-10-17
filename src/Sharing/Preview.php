<?php
/**
 * Exact, hash-bound transfer preview.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Sharing;

use MillionDollarScript\Extensions\ProjectCompanion\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class Preview {
    public const SCHEMA_VERSION = 1;

    /**
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    public static function create(string $module, array $field_ids, array $snapshot): array {
        $settings = Settings::get();
        $module = sanitize_key($module);
        if (empty($settings['modules'][$module])) {
            return [];
        }

        $definitions = FieldRegistry::all();
        $profile = $settings['profile'];
        $fields = [];
        foreach (array_values(array_unique(array_map(static fn($id): string => strtolower(trim((string) $id)), $field_ids))) as $id) {
            $definition = $definitions[$id] ?? null;
            if (!$definition || $definition['module'] !== $module || empty($settings['sharing'][$id])) {
                continue;
            }
            $fields[$id] = [
                'id' => $id,
                'source_module' => (string) $definition['source_module'],
                'purpose' => (string) $definition['contract_purpose'],
                'classification' => (string) $definition['classification'],
                'value' => self::value($id, $profile, $snapshot),
            ];
        }
        ksort($fields);
        if (!$fields) {
            return [];
        }

        $transfer_kind = 'create' === $module ? 'capability_snapshot' : 'project_context';
        $adapter = 'project_context' === $transfer_kind ? ContractPayload::project_adapter() : null;
        $capability_snapshot = 'capability_snapshot' === $transfer_kind
            ? ContractPayload::capability_snapshot($snapshot)
            : [];
        if ('capability_snapshot' === $transfer_kind) {
            $allowed = array_keys($fields);
            $required = ['core_api_version', 'extension_api_version', 'extensions', 'public_capabilities'];
            if (array_diff($required, $allowed)) {
                return [];
            }
            $fields = [];
            foreach (ContractPayload::capability_fields(ContractPayload::capability_snapshot($snapshot)) as $field) {
                $fields[$field['id']] = $field;
            }
            ksort($fields);
        }

        $preview = [
            'schema_version' => self::SCHEMA_VERSION,
            'module' => $module,
            'transfer_kind' => $transfer_kind,
            'field_ids' => array_keys($fields),
            'fields' => array_values($fields),
            'created_at' => gmdate('c'),
        ];
        if (null !== $adapter) {
            $preview['adapter'] = $adapter;
        }
        if ($capability_snapshot) {
            $preview['snapshot'] = $capability_snapshot;
        }
        $preview['hash'] = ContractPayload::preview_hash(
            $transfer_kind,
            array_values($fields),
            $adapter
        );
        return $preview;
    }

    public static function valid(array $preview, string $submitted_hash): bool {
        $hash = (string) ($preview['hash'] ?? '');
        $computed = ContractPayload::preview_hash(
            (string) ($preview['transfer_kind'] ?? ''),
            array_values((array) ($preview['fields'] ?? [])),
            isset($preview['adapter']) && is_array($preview['adapter']) ? $preview['adapter'] : null
        );
        return '' !== $hash
            && hash_equals($hash, $computed)
            && hash_equals($hash, sanitize_text_field($submitted_hash));
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $snapshot
     * @return mixed
     */
    private static function value(string $id, array $profile, array $snapshot) {
        return match ($id) {
            'project.name' => $profile['name'] ?? '',
            'project.summary' => $profile['summary'] ?? '',
            'project.type' => $profile['focus'] ?? 'unspecified',
            'project.goals' => $profile['goals'] ?? [],
            'project.constraints' => [
                'funding' => $profile['funding'] ?? 'unspecified',
                'external_services' => $profile['external_services'] ?? 'undecided',
            ],
            'project.resources' => [
                'setup_effort' => $profile['setup_effort'] ?? 'moderate',
            ],
            'core_api_version' => $snapshot['core']['version'] ?? '',
            'extension_api_version' => $snapshot['core']['extension_api_version'] ?? '',
            'extensions' => $snapshot['extensions'] ?? [],
            'public_capabilities' => $snapshot['active_capabilities'] ?? [],
            default => null,
        };
    }
}
