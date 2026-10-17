<?php
/**
 * Fields that may be included in a future approved transfer.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Sharing;

if (!defined('ABSPATH')) {
    exit;
}

final class FieldRegistry {
    /**
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array {
        return [
            'project.name' => self::field('project.name', 'plan', __('Name the bound project.', 'mds-project-companion'), false, false, 'name', 'Name the bound project'),
            'project.summary' => self::field('project.summary', 'plan', __('Give the hosted plan administrator-approved project context.', 'mds-project-companion'), true, false, 'summary', 'Describe the bound project'),
            'project.type' => self::field('project.type', 'plan', __('Distinguish local, online, hybrid, and unspecified projects.', 'mds-project-companion'), false, false, 'focus', 'Describe the project type'),
            'project.goals' => self::field('project.goals', 'plan', __('Tailor plans and extension guidance to selected goals.', 'mds-project-companion'), false, false, 'goals', 'Describe administrator-selected project goals'),
            'project.constraints' => self::field('project.constraints', 'plan', __('Share approved funding and external-service preferences.', 'mds-project-companion'), false, false, 'constraints', 'Describe administrator-selected constraints'),
            'project.resources' => self::field('project.resources', 'plan', __('Share the approved setup-effort preference.', 'mds-project-companion'), false, false, 'resources', 'Describe available project resources'),
            'core_api_version' => self::field('core_api_version', 'create', __('Match hosted guidance to the installed Million Dollar Script version.', 'mds-project-companion'), false, true, 'core', 'Match hosted guidance to the installed Million Dollar Script version'),
            'extension_api_version' => self::field('extension_api_version', 'create', __('Negotiate the public extension API schema.', 'mds-project-companion'), false, true, 'core', 'Negotiate the public extension API schema'),
            'extensions' => self::field('extensions', 'create', __('Share approved extension runtime state.', 'mds-project-companion'), false, true, 'extensions', 'Describe administrator-approved extension runtime state'),
            'public_capabilities' => self::field('public_capabilities', 'create', __('Share active public capabilities.', 'mds-project-companion'), false, true, 'capabilities', 'Describe active public capabilities'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function field(
        string $id,
        string $module,
        string $purpose,
        bool $personal,
        bool $required,
        string $source,
        string $contract_purpose
    ): array {
        return [
            'id' => $id,
            'module' => $module,
            'purpose' => $purpose,
            'could_contain_personal_data' => $personal,
            'required' => $required,
            'source' => $source,
            'source_module' => 'companion',
            'classification' => 'internal',
            'contract_purpose' => $contract_purpose,
            'known_retention' => __('Project lifecycle, under the hosted connector contract.', 'mds-project-companion'),
        ];
    }
}
