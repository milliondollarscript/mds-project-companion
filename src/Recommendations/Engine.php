<?php
/**
 * Deterministic local extension recommendation engine.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Recommendations;

if (!defined('ABSPATH')) {
    exit;
}
final class Engine {
    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $snapshot
     * @return array<int,array<string,mixed>>
     */
    public static function recommend(array $profile, array $snapshot, string $filter = 'best-fit'): array {
        $runtime = [];
        foreach ((array) ($snapshot['extensions'] ?? []) as $extension) {
            if (is_array($extension) && !empty($extension['slug'])) {
                $runtime[(string) $extension['slug']] = $extension;
            }
        }

        $goals = array_values(array_map('sanitize_key', (array) ($profile['goals'] ?? [])));
        $effort_rank = ['simple' => 1, 'moderate' => 2, 'advanced' => 3];
        $max_effort = $effort_rank[(string) ($profile['setup_effort'] ?? 'moderate')] ?? 2;
        $results = [];

        foreach (Catalogue::all() as $slug => $item) {
            $state = $runtime[$slug] ?? [];
            $item['installed'] = !empty($state['installed']);
            $item['active'] = !empty($state['active']);
            $item['available'] = $state['available'] ?? 'unknown';
            $item['dependencies'] = array_values((array) ($state['requires'] ?? []));
            $item['conflicts'] = array_values((array) ($state['conflicts'] ?? []));
            $item['requires_service'] = array_key_exists('requires_service', $state)
                ? (bool) $state['requires_service']
                : (bool) $item['requires_service'];

            $matched = array_values(array_intersect($goals, (array) $item['goals']));
            $score = count($matched) * 20;
            if ('local' === ($profile['focus'] ?? '') && in_array('local', $item['goals'], true)) {
                $score += 12;
            }
            if (in_array((string) ($profile['funding'] ?? ''), ['one-time', 'both'], true) && in_array('funding', $item['goals'], true)) {
                $score += 8;
            }
            if (in_array((string) ($profile['funding'] ?? ''), ['recurring', 'both'], true) && in_array('recurring', $item['goals'], true)) {
                $score += 10;
            }
            if ($item['installed']) {
                $score += 5;
            }
            if (($effort_rank[$item['setup_effort']] ?? 2) > $max_effort) {
                $score -= 8;
            }
            if ('no' === ($profile['external_services'] ?? '') && $item['requires_service']) {
                $score -= 30;
            }
            $item['score'] = $score;
            $item['why'] = $matched
                ? sprintf(__('Matches these project goals: %s.', 'mds-project-companion'), implode(', ', $matched))
                : __('Provides an optional workflow that may complement the current project profile.', 'mds-project-companion');

            if ('installed' === $filter && !$item['installed']) {
                continue;
            }
            if ('free' === $filter && 'free' !== $item['commercial']) {
                continue;
            }
            if ('no-services' === $filter && $item['requires_service']) {
                continue;
            }
            $results[] = $item;
        }

        usort($results, static function (array $left, array $right): int {
            $score = ((int) $right['score']) <=> ((int) $left['score']);
            return 0 !== $score ? $score : strcasecmp((string) $left['name'], (string) $right['name']);
        });
        return $results;
    }

    /**
     * @param array<int,array<string,mixed>> $recommendations
     * @return array<int,string>
     */
    public static function checklist(array $recommendations): array {
        $items = [
            __('Review and save the optional project profile.', 'mds-project-companion'),
            __('Review the capability snapshot and refresh available-extension data when needed.', 'mds-project-companion'),
        ];
        foreach (array_slice($recommendations, 0, 3) as $item) {
            $items[] = sprintf(
                __('Review %1$s and confirm its dependencies before changing your site.', 'mds-project-companion'),
                (string) $item['name']
            );
        }
        $items[] = __('Use the Million Dollar Script Extensions page for any installation or activation decision.', 'mds-project-companion');
        return $items;
    }
}
