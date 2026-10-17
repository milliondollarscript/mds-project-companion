<?php
/**
 * Bounded JSON response validation for connector contract v1.0.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform\Http;

if (!defined('ABSPATH')) {
    exit;
}

final class ResponseGuard {
    /**
     * @return array<string,mixed>|\WP_Error
     */
    public static function decode(string $body, string $schema = '') {
        if (strlen($body) > EndpointPolicy::MAX_RESPONSE_BYTES) {
            return new \WP_Error('response_too_large', __('The hosted response exceeded the permitted size.', 'mds-project-companion'));
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || JSON_ERROR_NONE !== json_last_error()) {
            return new \WP_Error('invalid_json', __('The hosted response was not valid JSON.', 'mds-project-companion'));
        }
        if ('' !== $schema && !self::valid($schema, $decoded)) {
            return new \WP_Error('invalid_schema', __('The hosted response did not match the negotiated connector schema.', 'mds-project-companion'));
        }
        return $decoded;
    }

    /**
     * Validate the response boundaries used by the client.
     *
     * @param array<string,mixed> $data
     */
    public static function valid(string $schema, array $data): bool {
        $validators = [
            'capability_discovery' => static function (array $value): bool {
                if (!(
                    self::exact_keys($value, ['api_majors', 'connector_contracts', 'capabilities', 'scopes', 'limits', 'minimum_clients'], ['deprecated_after'])
                    && self::string_list($value['api_majors'])
                    && self::string_list($value['connector_contracts'])
                    && self::string_list($value['scopes'])
                    && is_array($value['capabilities'])
                    && is_array($value['limits'])
                    && is_array($value['minimum_clients'])
                )) {
                    return false;
                }
                foreach ($value['capabilities'] as $capability) {
                    if (!self::operation_capability($capability)) {
                        return false;
                    }
                }
                foreach ($value['limits'] as $limit) {
                    if (!is_int($limit) || $limit < 1) {
                        return false;
                    }
                }
                foreach ($value['minimum_clients'] as $minimum) {
                    if (!is_string($minimum)) {
                        return false;
                    }
                }
                return !isset($value['deprecated_after']) || self::date_time($value['deprecated_after']);
            },
            'pairing_challenge' => static function (array $value): bool {
                return self::exact_keys($value, ['pairing_id', 'pairing_secret', 'user_code', 'verification_path', 'expires_at', 'poll_after_seconds'])
                    && self::uuid($value['pairing_id'])
                    && strlen((string) $value['pairing_secret']) >= 32
                    && preg_match('/^[A-Z2-9]{4}-[A-Z2-9]{4}$/', (string) $value['user_code'])
                    && '/settings/connectors/pair' === $value['verification_path']
                    && self::date_time($value['expires_at'])
                    && (int) $value['poll_after_seconds'] >= 2
                    && (int) $value['poll_after_seconds'] <= 60;
            },
            'pairing_result' => static function (array $value): bool {
                return self::exact_keys($value, ['installation', 'credential'])
                    && is_array($value['installation'])
                    && self::valid('installation', $value['installation'])
                    && is_array($value['credential'])
                    && self::valid('credential_result', $value['credential']);
            },
            'installation' => static function (array $value): bool {
                return self::exact_keys($value, ['installation_id', 'organization_id', 'project_id', 'installation_key', 'state', 'contract_version', 'schema_versions', 'scopes', 'connected_at'], ['revoked_at'])
                    && self::uuid($value['installation_id'])
                    && self::uuid($value['organization_id'])
                    && self::uuid($value['project_id'])
                    && preg_match('/^[A-Za-z0-9._:-]{16,200}$/', (string) $value['installation_key'])
                    && in_array($value['state'], ['connected', 'revoked'], true)
                    && self::schema_versions($value['schema_versions'])
                    && self::string_list($value['scopes'])
                    && self::date_time($value['connected_at'])
                    && (!isset($value['revoked_at']) || self::date_time($value['revoked_at']));
            },
            'connection_test' => static function (array $value): bool {
                return self::exact_keys($value, ['status', 'installation_id', 'contract_version', 'schema_versions', 'scopes', 'credential_expires_at'])
                    && 'connected' === $value['status']
                    && self::uuid($value['installation_id'])
                    && self::schema_versions($value['schema_versions'])
                    && self::string_list($value['scopes'])
                    && self::date_time($value['credential_expires_at']);
            },
            'credential_result' => static function (array $value): bool {
                return self::exact_keys($value, ['credential', 'expires_at'])
                    && strlen((string) $value['credential']) >= 32
                    && strlen((string) $value['credential']) <= 200
                    && self::date_time($value['expires_at']);
            },
            'revocation_result' => static function (array $value): bool {
                return self::exact_keys($value, ['installation_id', 'revoked_at', 'replayed'])
                    && self::uuid($value['installation_id'])
                    && self::date_time($value['revoked_at'])
                    && is_bool($value['replayed']);
            },
            'transfer_result' => static function (array $value): bool {
                return self::exact_keys($value, ['request_id', 'payload_digest', 'schema_version', 'fields', 'replayed'])
                    && preg_match('/^req_[A-Za-z0-9_-]{8,120}$/', (string) $value['request_id'])
                    && self::digest($value['payload_digest'])
                    && 1 === (int) $value['schema_version']
                    && self::field_results($value['fields'])
                    && is_bool($value['replayed']);
            },
            'plan_view' => static fn(array $value): bool => self::versioned_view($value, ['project', 'plan_state', 'recommendations_available'], ['plan'])
                && in_array($value['plan_state'], ['unpublished', 'published'], true)
                && self::project_view($value['project'])
                && is_bool($value['recommendations_available'])
                && (!isset($value['plan']) || self::published_plan($value['plan'])),
            'recommendation_view' => static fn(array $value): bool => self::versioned_view($value, ['algorithm_version', 'source_digest', 'recommendations'])
                && self::digest($value['source_digest'])
                && self::recommendations($value['recommendations']),
            'create_view' => static fn(array $value): bool => self::versioned_view($value, ['learning_tracks', 'prompt_packs', 'milestones'])
                && self::journey_items($value['learning_tracks'])
                && self::journey_items($value['prompt_packs'])
                && self::journey_items($value['milestones']),
            'draft_result' => static function (array $value): bool {
                return self::exact_keys($value, ['draft_id', 'draft_kind', 'state', 'fields', 'payload_digest', 'replayed'])
                    && self::uuid($value['draft_id'])
                    && in_array($value['draft_kind'], ['product', 'offer'], true)
                    && 'draft' === $value['state']
                    && self::field_results($value['fields'])
                    && is_string($value['payload_digest'])
                    && '' !== $value['payload_digest']
                    && is_bool($value['replayed']);
            },
            'distribution_view' => static fn(array $value): bool => self::versioned_view($value, ['destinations', 'promotions', 'releases'])
                && self::summary_items($value['destinations'])
                && self::summary_items($value['promotions'])
                && self::summary_items($value['releases']),
            'growth_view' => static fn(array $value): bool => self::versioned_view($value, ['experiments', 'findings', 'next_actions'])
                && self::summary_items($value['experiments'])
                && self::summary_items($value['findings'])
                && self::recommendations($value['next_actions']),
            'acknowledgement_result' => static function (array $value): bool {
                return self::exact_keys($value, ['action_id', 'state', 'acknowledged_at', 'replayed'])
                    && self::uuid($value['action_id'])
                    && in_array($value['state'], ['seen', 'accepted_for_manual_review', 'dismissed'], true)
                    && self::date_time($value['acknowledged_at'])
                    && is_bool($value['replayed']);
            },
            'problem' => static function (array $value): bool {
                $codes = [
                    'authentication_failed', 'authorization_denied', 'installation_revoked',
                    'rate_limited', 'validation_failed', 'schema_mismatch', 'request_too_large',
                    'dependency_unavailable', 'idempotency_conflict', 'replay_rejected', 'not_found',
                ];
                return self::exact_keys($value, ['type', 'title', 'status', 'detail', 'request_id', 'code', 'retryable'])
                    && self::uri($value['type'])
                    && (int) $value['status'] >= 400
                    && (int) $value['status'] <= 599
                    && in_array($value['code'], $codes, true)
                    && is_bool($value['retryable']);
            },
        ];
        return isset($validators[$schema]) && $validators[$schema]($data);
    }

    /**
     * @param array<string,mixed> $value
     * @param array<int,string> $required
     * @param array<int,string> $optional
     */
    private static function exact_keys(array $value, array $required, array $optional = []): bool {
        foreach ($required as $key) {
            if (!array_key_exists($key, $value)) {
                return false;
            }
        }
        return !array_diff(array_keys($value), array_merge($required, $optional));
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
     * @param mixed $value
     */
    private static function uuid($value): bool {
        return is_string($value)
            && 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
    }

    /**
     * @param mixed $value
     */
    private static function date_time($value): bool {
        return is_string($value) && false !== strtotime($value);
    }

    /**
     * @param mixed $value
     */
    private static function digest($value): bool {
        return is_string($value) && 1 === preg_match('/^[a-f0-9]{64}$/', $value);
    }

    /**
     * @param mixed $value
     */
    private static function uri($value): bool {
        return is_string($value)
            && 1 === preg_match('/^[a-z][a-z0-9+.-]*:[^\s]+$/i', $value);
    }

    /**
     * @param array<string,mixed> $value
     * @param array<int,string> $required
     * @param array<int,string> $optional
     */
    private static function versioned_view(array $value, array $required, array $optional = []): bool {
        return self::exact_keys($value, array_merge(['schema_version'], $required), $optional)
            && 1 === (int) $value['schema_version'];
    }

    /**
     * @param mixed $value
     */
    private static function schema_versions($value): bool {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $key => $version) {
            if (!is_string($key) || !is_int($version) || $version < 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     */
    private static function operation_capability($value): bool {
        if (
            !is_array($value)
            || !self::exact_keys($value, ['operation', 'schema_versions', 'mode'])
            || !is_string($value['operation'])
            || '' === $value['operation']
            || !is_array($value['schema_versions'])
            || !$value['schema_versions']
            || !in_array($value['mode'], [
                'read-only', 'pairing', 'credential-lifecycle',
                'consent-bound-write', 'draft-only', 'acknowledgement-only',
            ], true)
        ) {
            return false;
        }
        foreach ($value['schema_versions'] as $version) {
            if (!is_int($version) || $version < 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     */
    private static function field_results($value): bool {
        if (!is_array($value) || count($value) > 200) {
            return false;
        }
        foreach ($value as $field) {
            if (
                !is_array($field)
                || !self::exact_keys($field, ['field_id', 'status'], ['reason_code'])
                || !is_string($field['field_id'])
                || !in_array($field['status'], ['accepted', 'rejected'], true)
                || isset($field['reason_code']) && !in_array($field['reason_code'], [
                    'not_selected', 'unknown_field', 'invalid_value',
                    'prohibited_classification', 'unsupported_schema',
                ], true)
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     */
    private static function project_view($value): bool {
        return is_array($value)
            && self::exact_keys($value, ['id', 'name', 'type', 'summary', 'revision', 'updated_at'])
            && self::uuid($value['id'])
            && is_string($value['name'])
            && is_string($value['type'])
            && is_string($value['summary'])
            && is_int($value['revision'])
            && $value['revision'] >= 1
            && self::date_time($value['updated_at']);
    }

    /**
     * @param mixed $value
     */
    private static function published_plan($value): bool {
        if (
            !is_array($value)
            || !self::exact_keys($value, ['version_id', 'branch_id', 'version', 'content_hash', 'published_at', 'items'])
            || !self::uuid($value['version_id'])
            || !self::uuid($value['branch_id'])
            || !is_int($value['version'])
            || $value['version'] < 1
            || !self::digest($value['content_hash'])
            || !self::date_time($value['published_at'])
            || !is_array($value['items'])
            || count($value['items']) > 500
        ) {
            return false;
        }
        foreach ($value['items'] as $item) {
            if (
                !is_array($item)
                || !self::exact_keys($item, ['id', 'kind', 'title', 'body', 'position'])
                || !self::uuid($item['id'])
                || !is_string($item['kind'])
                || !is_string($item['title'])
                || !is_string($item['body'])
                || !is_int($item['position'])
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     */
    private static function recommendations($value): bool {
        if (!is_array($value) || count($value) > 100) {
            return false;
        }
        foreach ($value as $item) {
            if (
                !is_array($item)
                || !self::exact_keys($item, ['id', 'kind', 'title', 'rationale', 'provenance'])
                || !is_string($item['id'])
                || !in_array($item['kind'], ['extension', 'workflow', 'hosted_next_action'], true)
                || !is_string($item['title'])
                || !self::string_list($item['rationale'])
                || !$item['rationale']
                || count($item['rationale']) > 20
                || !self::string_list($item['provenance'])
                || !$item['provenance']
                || count($item['provenance']) > 50
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     */
    private static function journey_items($value): bool {
        if (!is_array($value) || count($value) > 200) {
            return false;
        }
        foreach ($value as $item) {
            if (
                !is_array($item)
                || !self::exact_keys($item, ['id', 'title', 'state', 'rationale', 'provenance'])
                || !is_string($item['id'])
                || !is_string($item['title'])
                || !is_string($item['state'])
                || !is_string($item['rationale'])
                || !self::string_list($item['provenance'])
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param mixed $value
     */
    private static function summary_items($value): bool {
        if (!is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (
                !is_array($item)
                || !self::exact_keys($item, ['id', 'title', 'state', 'provenance'])
                || !is_string($item['id'])
                || !is_string($item['title'])
                || !is_string($item['state'])
                || !self::string_list($item['provenance'])
            ) {
                return false;
            }
        }
        return true;
    }
}
