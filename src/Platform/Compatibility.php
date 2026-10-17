<?php
/**
 * Fail-closed connector discovery compatibility checks.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform;

if (!defined('ABSPATH')) {
    exit;
}

final class Compatibility {
    /**
     * @param array<string,mixed> $discovery
     * @param array<int,string>   $required_scopes
     * @return array{compatible:bool,reasons:array<int,string>}
     */
    public static function evaluate(array $discovery, array $required_scopes): array {
        $reasons = [];
        if (!in_array(ConnectorContract::API_MAJOR, (array) ($discovery['api_majors'] ?? []), true)) {
            $reasons[] = 'unsupported_api_major';
        }
        if (!in_array(ConnectorContract::VERSION, (array) ($discovery['connector_contracts'] ?? []), true)) {
            $reasons[] = 'unsupported_contract';
        }

        $operations = [];
        foreach ((array) ($discovery['capabilities'] ?? []) as $capability) {
            if (!is_array($capability) || empty($capability['operation'])) {
                continue;
            }
            $operations[(string) $capability['operation']] = array_map('intval', (array) ($capability['schema_versions'] ?? []));
        }
        foreach ((array) ($discovery['schema_versions'] ?? []) as $operation => $versions) {
            if (!isset($operations[$operation])) {
                $operations[(string) $operation] = array_map('intval', (array) $versions);
            }
        }
        foreach (ConnectorContract::SCHEMAS as $schema => $version) {
            if (!isset($operations[$schema]) || !in_array($version, $operations[$schema], true)) {
                $reasons[] = 'unsupported_schema:' . $schema;
            }
        }

        $available_scopes = (array) ($discovery['scopes'] ?? []);
        foreach ($required_scopes as $scope) {
            if (!in_array($scope, $available_scopes, true)) {
                $reasons[] = 'unsupported_scope:' . $scope;
            }
        }

        $minimum = (string) (($discovery['minimum_clients']['mds-project-companion'] ?? '0.0.0'));
        if (version_compare(MDS_PROJECT_COMPANION_VERSION, $minimum, '<')) {
            $reasons[] = 'client_upgrade_required';
        }

        return ['compatible' => !$reasons, 'reasons' => $reasons];
    }
}
