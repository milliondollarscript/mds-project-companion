<?php
/**
 * Safe export adapter registry.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Export;

if (!defined('ABSPATH')) {
    exit;
}

final class AdapterRegistry {
    /** @var array<string,ExportAdapter> */
    private array $adapters = [];

    public function register(ExportAdapter $adapter): bool {
        $type = sanitize_key($adapter->object_type());
        if ('' === $type || isset($this->adapters[$type])) {
            return false;
        }
        $contract = trim($adapter->required_public_contract());
        if (
            '' !== $contract
            && 'mds-project-companion' !== $adapter->owner()
            && !apply_filters('million-dollar-script/project-companion/public-contract-available', false, $contract)
        ) {
            return false;
        }
        $this->adapters[$type] = $adapter;
        return true;
    }

    public function get(string $type): ?ExportAdapter {
        return $this->adapters[sanitize_key($type)] ?? null;
    }

    /**
     * @return array<string,ExportAdapter>
     */
    public function all(): array {
        ksort($this->adapters);
        return $this->adapters;
    }
}
