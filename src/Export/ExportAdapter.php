<?php
/**
 * Extension-owned export adapter contract.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Export;

if (!defined('ABSPATH')) {
    exit;
}
interface ExportAdapter {
    public function object_type(): string;
    public function owner(): string;
    public function available(): bool;
    public function required_public_contract(): string;
    public function required_capability(): string;
    public function schema_version(): int;
    public function list_items(): array;
    public function preview(string $id, array $selected_fields): array;
    public function validate(array $payload): bool;
}
