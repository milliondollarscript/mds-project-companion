<?php
/**
 * Generic administrator-entered project context adapter.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Export;

use MillionDollarScript\Extensions\ProjectCompanion\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class ProjectContextAdapter implements ExportAdapter {
    public function object_type(): string { return 'project'; }
    public function owner(): string { return 'mds-project-companion'; }
    public function available(): bool { return current_user_can('manage_options'); }
    public function required_public_contract(): string { return 'mds.public.project-context.v1'; }
    public function required_capability(): string { return 'manage_options'; }
    public function schema_version(): int { return 1; }

    public function list_items(): array {
        $profile = Settings::get()['profile'];
        return [[
            'id' => 'site-project',
            'label' => (string) ($profile['name'] ?: __('Site project', 'mds-project-companion')),
        ]];
    }

    public function preview(string $id, array $selected_fields): array {
        if ('site-project' !== $id || !$this->available()) {
            return [];
        }
        $profile = Settings::get()['profile'];
        $allowed = ['name', 'summary', 'goals', 'focus', 'funding', 'external_services', 'setup_effort'];
        return array_intersect_key($profile, array_flip(array_intersect($allowed, array_map('sanitize_key', $selected_fields))));
    }

    public function validate(array $payload): bool {
        return 1 === (int) ($payload['schema_version'] ?? 0)
            && 'project' === ($payload['object_type'] ?? '')
            && is_array($payload['data'] ?? null);
    }
}
