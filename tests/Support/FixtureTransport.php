<?php
/**
 * Queue-backed transport for connector compatibility tests.
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Tests\Support;

use MillionDollarScript\Extensions\ProjectCompanion\Platform\Http\Transport;

final class FixtureTransport implements Transport {
    /** @var array<int,array<string,mixed>|\WP_Error> */
    private array $responses;

    /** @var array<int,array{url:string,arguments:array<string,mixed>}> */
    public array $requests = [];

    /**
     * @param array<int,array<string,mixed>|\WP_Error> $responses
     */
    public function __construct(array $responses) {
        $this->responses = array_values($responses);
    }

    /**
     * @param array<string,mixed> $arguments
     * @return array<string,mixed>|\WP_Error
     */
    public function request(string $url, array $arguments) {
        $this->requests[] = ['url' => $url, 'arguments' => $arguments];
        return array_shift($this->responses) ?? new \WP_Error('fixture_exhausted', 'No fixture response remains.');
    }
}
