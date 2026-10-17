<?php
/**
 * Brand-neutral platform client result.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform;

if (!defined('ABSPATH')) {
    exit;
}
final class ClientResult {
    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly string $request_id = '',
        public readonly array $data = [],
        public readonly string $error_code = '',
        public readonly string $message = ''
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function to_array(): array {
        return [
            'success' => $this->success,
            'status' => $this->status,
            'request_id' => $this->request_id,
            'data' => $this->data,
            'error_code' => $this->error_code,
            'message' => $this->message,
        ];
    }
}
