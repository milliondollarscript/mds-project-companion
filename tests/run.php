<?php
/**
 * Focused dependency-free unit checks.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('DAY_IN_SECONDS', 86400);
define('MINUTE_IN_SECONDS', 60);
define('MDS_PROJECT_COMPANION_PATH', dirname(__DIR__) . '/');
define('MDS_PROJECT_COMPANION_VERSION', '0.2.0');
define('MDS_PROJECT_COMPANION_DB_VERSION', '2');

$GLOBALS['test_options'] = [];
$GLOBALS['test_transients'] = [];

function __($text) { return $text; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\\-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return trim(strip_tags((string) $value)); }
function wp_strip_all_tags($value) { return strip_tags((string) $value); }
function wp_parse_url($value) { return parse_url($value); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_salt($scheme = 'auth') { return 'test-salt-' . $scheme; }
function absint($value) { return abs((int) $value); }
function get_option($key, $default = false) { return $GLOBALS['test_options'][$key] ?? $default; }
function update_option($key, $value, $autoload = null) { $GLOBALS['test_options'][$key] = $value; return true; }
function delete_option($key) { unset($GLOBALS['test_options'][$key]); return true; }
function set_transient($key, $value, $expiration = 0) { $GLOBALS['test_transients'][$key] = $value; return true; }
function get_transient($key) { return $GLOBALS['test_transients'][$key] ?? false; }
function delete_transient($key) { unset($GLOBALS['test_transients'][$key]); return true; }
function wp_generate_uuid4() { return '123e4567-e89b-42d3-a456-426614174000'; }
function wp_parse_args($args, $defaults = []) { return array_merge($defaults, is_array($args) ? $args : []); }
function current_user_can($capability) { return 'manage_options' === $capability; }
function apply_filters($hook, $value, ...$args) { return $value; }
function untrailingslashit($value) { return rtrim((string) $value, '/\\'); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body($response) { return (string) ($response['body'] ?? ''); }

class WP_Error {
    public function __construct(private string $code, private string $message) {}
    public function get_error_code(): string { return $this->code; }
    public function get_error_message(): string { return $this->message; }
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MillionDollarScript\\Extensions\\ProjectCompanion\\';
    if (0 !== strpos($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = dirname(__DIR__) . '/src/' . $relative . '.php';
    if (is_readable($path)) {
        require_once $path;
    }
});

require_once __DIR__ . '/Support/MockClient.php';
require_once __DIR__ . '/Support/FixtureTransport.php';

use MillionDollarScript\Extensions\ProjectCompanion\Audit\Redactor;
use MillionDollarScript\Extensions\ProjectCompanion\Export\AdapterRegistry;
use MillionDollarScript\Extensions\ProjectCompanion\Export\ExportAdapter;
use MillionDollarScript\Extensions\ProjectCompanion\Export\ProjectContextAdapter;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\DisabledClient;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\Compatibility;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\ConnectionRepository;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\ConnectorContract;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\Http\EndpointPolicy;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\Http\HttpClient;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\Http\RequestGuard;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\Http\ResponseGuard;
use MillionDollarScript\Extensions\ProjectCompanion\Lifecycle\Installer;
use MillionDollarScript\Extensions\ProjectCompanion\Recommendations\Catalogue;
use MillionDollarScript\Extensions\ProjectCompanion\Recommendations\Engine;
use MillionDollarScript\Extensions\ProjectCompanion\Settings;
use MillionDollarScript\Extensions\ProjectCompanion\Sharing\Preview;
use MillionDollarScript\Extensions\ProjectCompanion\Sharing\ContractPayload;
use MillionDollarScript\Extensions\ProjectCompanion\Tests\Support\FixtureTransport;
use MillionDollarScript\Extensions\ProjectCompanion\Tests\Support\MockClient;

$tests = [];
$test = static function (string $name, callable $callback) use (&$tests): void {
    try {
        $callback();
        $tests[] = ['name' => $name, 'ok' => true];
    } catch (Throwable $error) {
        $tests[] = ['name' => $name, 'ok' => false, 'message' => $error->getMessage()];
    }
};
$assert = static function ($condition, string $message = 'Assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$test('module defaults and sanitization', static function () use ($assert): void {
    $settings = Settings::sanitize([]);
    $assert($settings['modules'] === ['plan' => false, 'create' => false, 'distribute' => false, 'grow' => false]);
    $assert(365 === $settings['retention_days']);
    $assert(!array_key_exists('cleanup_included', $settings));
    $changed = Settings::sanitize(['modules' => ['plan' => '1'], 'retention_days' => '99999']);
    $assert(true === $changed['modules']['plan'] && false === $changed['modules']['create']);
    $assert(3650 === $changed['retention_days']);
    $sharing = Settings::sanitize(['sharing' => ['project.name' => '1', 'bad key!' => '1']]);
    $assert(true === $sharing['sharing']['project.name'] && !isset($sharing['sharing']['bad key!']));
});

$test('sharing preferences migrate to connector field identifiers', static function () use ($assert): void {
    $GLOBALS['test_options'][Installer::DB_VERSION_OPTION] = '1';
    $GLOBALS['test_options'][Settings::OPTION] = array_merge(Settings::defaults(), [
        'sharing' => ['project_name' => true, 'mds_versions' => true, 'project_summary' => false],
    ]);
    Installer::maybe_upgrade();
    $sharing = Settings::get()['sharing'];
    $assert(!isset($sharing['project_name']) && !isset($sharing['mds_versions']));
    $assert(true === $sharing['project.name']);
    $assert(true === $sharing['core_api_version'] && true === $sharing['extension_api_version']);
    $assert('2' === $GLOBALS['test_options'][Installer::DB_VERSION_OPTION]);
});

$test('bootstrap readiness scenarios fail closed', static function () use ($assert): void {
    foreach (['missing', 'unready', 'unsupported', 'ready'] as $scenario) {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/bootstrap-scenario.php') . ' ' . escapeshellarg($scenario);
        $output = [];
        $exit = 0;
        exec($command, $output, $exit);
        $result = json_decode(implode('', $output), true);
        $assert(0 === $exit && is_array($result), "Bootstrap probe failed for {$scenario}.");
        if ('ready' === $scenario) {
            $assert(false === $result['notice'] && true === $result['registered'], 'Ready MDS did not register.');
        } else {
            $assert(true === $result['notice'] && false === $result['registered'], "{$scenario} did not fail closed.");
        }
    }
});

$test('audit secret filtering', static function () use ($assert): void {
    $clean = Redactor::clean(['api_key' => 'secret', 'nested' => ['Authorization' => 'Bearer abc'], 'safe' => 'ok']);
    $assert('[redacted]' === $clean['api_key']);
    $assert('[redacted]' === $clean['nested']['authorization']);
    $assert('ok' === $clean['safe']);
});

$test('disabled client never reports connected', static function () use ($assert): void {
    $result = (new DisabledClient())->test_connection();
    $assert(false === $result->success);
    $assert('disabled' === $result->status);
    $assert('hosted_api_not_configured' === $result->error_code);
});

$test('mock scenarios are deterministic', static function () use ($assert): void {
    $assert((new MockClient('success'))->test_connection()->success);
    $assert('request_timeout' === (new MockClient('timeout'))->test_connection()->error_code);
    $assert('invalid_json' === (new MockClient('invalid-json'))->test_connection()->error_code);
    $assert('response_too_large' === (new MockClient('oversized'))->test_connection()->error_code);
    $assert('unsupported_schema' === (new MockClient('schema-mismatch'))->test_connection()->error_code);
    $assert('credential_revoked' === (new MockClient('revoked'))->test_connection()->error_code);
    $assert('remote_failure' === (new MockClient('remote-error'))->test_connection()->error_code);
});

$test('endpoint policy enforces HTTPS and SSRF controls', static function () use ($assert): void {
    $assert(EndpointPolicy::validate('https://93.184.216.34'));
    $assert(!EndpointPolicy::validate('http://93.184.216.34'));
    $assert(!EndpointPolicy::validate('https://127.0.0.1'));
    $assert(EndpointPolicy::validate('http://127.0.0.1:3030', true));
    $assert(!EndpointPolicy::validate('https://user:pass@93.184.216.34'));
});

$test('response guard rejects invalid and unsupported payloads', static function () use ($assert): void {
    $assert(ResponseGuard::decode('{broken') instanceof WP_Error);
    $oversized = ResponseGuard::decode(str_repeat('x', EndpointPolicy::MAX_RESPONSE_BYTES + 1));
    $assert($oversized instanceof WP_Error && 'response_too_large' === $oversized->get_error_code());
    $schema = ResponseGuard::decode('{"schema_version":2,"learning_tracks":[],"prompt_packs":[],"milestones":[]}', 'create_view');
    $assert($schema instanceof WP_Error && 'invalid_schema' === $schema->get_error_code());
    $valid = '{"schema_version":1,"learning_tracks":[],"prompt_packs":[],"milestones":[]}';
    $assert(1 === ResponseGuard::decode($valid, 'create_view')['schema_version']);
});

$test('catalogue covers production extensions and free filter', static function () use ($assert): void {
    $assert(22 === count(Catalogue::all()));
    $results = Engine::recommend(['goals' => ['funding'], 'setup_effort' => 'moderate'], ['extensions' => []], 'free');
    $assert(13 === count($results));
    foreach ($results as $result) {
        $assert('free' === $result['commercial']);
    }
});

$test('runtime discovery controls recommendation state and contracts', static function () use ($assert): void {
    $snapshot = [
        'extensions' => [[
            'slug' => 'mds-missions',
            'installed' => true,
            'active' => false,
            'available' => true,
            'requires' => ['mds-launch-wall'],
            'conflicts' => ['example-conflict'],
            'requires_service' => true,
        ]],
    ];
    $results = Engine::recommend(['goals' => ['missions']], $snapshot, 'installed');
    $assert(1 === count($results));
    $assert('mds-missions' === $results[0]['slug']);
    $assert(true === $results[0]['installed'] && false === $results[0]['active']);
    $assert(true === $results[0]['available'] && true === $results[0]['requires_service']);
    $assert(['mds-launch-wall'] === $results[0]['dependencies']);
    $assert(['example-conflict'] === $results[0]['conflicts']);
});

$test('preview requires enabled module and fields', static function () use ($assert): void {
    $GLOBALS['test_options'][Settings::OPTION] = array_merge(Settings::defaults(), [
        'modules' => ['plan' => true, 'create' => false, 'distribute' => false, 'grow' => false],
        'sharing' => ['project.name' => true],
        'profile' => array_merge(Settings::defaults()['profile'], ['name' => 'Test project']),
    ]);
    $preview = Preview::create('plan', ['project.name', 'project.summary'], ['core' => []]);
    $assert(['project.name'] === $preview['field_ids']);
    $assert(Preview::valid($preview, $preview['hash']));
    $assert(!Preview::valid($preview, 'wrong'));
});

$test('platform-owned compatibility fixture is mirrored exactly', static function () use ($assert): void {
    $fixture_path = __DIR__ . '/fixtures/connector-v1/compatibility.json';
    $fixture = json_decode((string) file_get_contents($fixture_path), true);
    $assert(is_array($fixture), 'Vendored connector fixture is invalid.');
    $assert(ConnectorContract::VERSION === $fixture['contract_version']);
    $assert(ConnectorContract::API_MAJOR === $fixture['api_major']);
    $assert(ConnectorContract::SCHEMAS === array_map(static fn(array $versions): int => (int) $versions[0], $fixture['discovery']['schema_versions']));
    $assert(ConnectorContract::DEFERRED_CAPABILITIES === $fixture['discovery']['deferred_capabilities']);

    $request = $fixture['project_context_request'];
    $computed = ContractPayload::preview_hash('project_context', $request['fields'], $request['adapter']);
    $assert($request['preview_hash'] === $computed, 'Project-context preview hash drifted from the platform fixture.');
    $assert(RequestGuard::transfer('project_context', $request), 'Platform fixture request was rejected locally.');
    $assert(ResponseGuard::valid('transfer_result', $fixture['transfer_result']));
    $assert(ResponseGuard::valid('draft_result', $fixture['draft_result']));

    $platform_root = '/home/rwr/projects/unified-project-platform';
    if (is_readable($platform_root . '/api/connector-openapi.yaml')) {
        $assert(ConnectorContract::OPENAPI_SHA256 === hash_file('sha256', $platform_root . '/api/connector-openapi.yaml'));
        $assert(ConnectorContract::FIXTURE_SHA256 === hash_file('sha256', $platform_root . '/api/fixtures/connector-v1/compatibility.json'));
        $assert(hash_file('sha256', $fixture_path) === hash_file('sha256', $platform_root . '/api/fixtures/connector-v1/compatibility.json'));
        $openapi = (string) file_get_contents($platform_root . '/api/connector-openapi.yaml');
        $assert(str_contains($openapi, '  - url: ' . ConnectorContract::SERVER_PATH));
        foreach (ConnectorContract::PATHS as $path) {
            $assert(str_contains($openapi, '  ' . $path . ':'), "Contract path {$path} is not authoritative.");
        }
    }
});

$test('compatibility negotiation fails closed', static function () use ($assert): void {
    $fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/connector-v1/compatibility.json'), true);
    $discovery = $fixture['discovery'] + [
        'capabilities' => [],
        'scopes' => array_values(ConnectorContract::SCOPES),
        'limits' => ['request_bytes' => 262144],
        'minimum_clients' => ['mds-project-companion' => '0.2.0'],
    ];
    $compatible = Compatibility::evaluate($discovery, [ConnectorContract::SCOPES['plan_read']]);
    $assert($compatible['compatible']);
    $discovery['api_majors'] = ['v2'];
    $incompatible = Compatibility::evaluate($discovery, [ConnectorContract::SCOPES['plan_read']]);
    $assert(!$incompatible['compatible'] && in_array('unsupported_api_major', $incompatible['reasons'], true));
});

$test('module scopes are deterministic and least privilege', static function () use ($assert): void {
    $base = ConnectorContract::scopes_for_modules([]);
    $assert(3 === count($base));
    $plan = ConnectorContract::scopes_for_modules(['plan' => true]);
    $assert(in_array('connector:context:write', $plan, true));
    $assert(in_array('connector:plan:read', $plan, true));
    $assert(!in_array('connector:drafts:write', $plan, true));
    $all = ConnectorContract::scopes_for_modules(
        ['plan' => true, 'create' => true, 'distribute' => true, 'grow' => true],
        ['draft' => true, 'metrics' => true]
    );
    $assert(count(ConnectorContract::SCOPES) === count($all));
});

$test('capability snapshots require all exact preview-bound fields', static function () use ($assert): void {
    $GLOBALS['test_options'][Settings::OPTION] = array_merge(Settings::defaults(), [
        'modules' => ['plan' => false, 'create' => true, 'distribute' => false, 'grow' => false],
        'sharing' => [
            'core_api_version' => true,
            'extension_api_version' => true,
            'extensions' => true,
            'public_capabilities' => true,
        ],
    ]);
    $snapshot = [
        'core' => ['version' => '3.0.0', 'extension_api_version' => '1'],
        'extensions' => [[
            'slug' => 'mds-example',
            'version' => '1.2.3',
            'installed' => true,
            'active' => true,
            'provides' => ['example.public'],
            'registration_capabilities' => [],
            'requires' => [],
            'conflicts' => [],
            'requires_service' => false,
        ]],
        'active_capabilities' => ['example.public'],
    ];
    $ids = ['core_api_version', 'extension_api_version', 'extensions', 'public_capabilities'];
    $preview = Preview::create('create', $ids, $snapshot);
    $assert($preview && 'capability_snapshot' === $preview['transfer_kind']);
    $payload = ContractPayload::approved($preview);
    $assert(RequestGuard::transfer('capability_snapshot', $payload));
    $payload['snapshot']['repository_url'] = 'https://example.test/private';
    $assert(!RequestGuard::transfer('capability_snapshot', $payload), 'Unknown snapshot data was accepted.');
});

$test('draft and metric guards preserve draft-only and aggregate boundaries', static function () use ($assert): void {
    $adapter = [
        'adapter_key' => 'fixture-draft-adapter',
        'public_contract' => 'fixture.public.draft.v1',
        'schema_version' => 1,
        'source_object_type' => 'fixture',
        'source_object_id' => 'fixture-1',
    ];
    $fields = [[
        'id' => 'name',
        'source_module' => 'companion',
        'purpose' => 'Name the draft',
        'classification' => 'internal',
        'value' => 'Fixture draft',
    ]];
    $draft = [
        'schema_version' => 1,
        'preview_hash' => ContractPayload::draft_preview_hash('product', $adapter, $fields),
        'selected_field_ids' => ['name'],
        'consented_at' => gmdate('c'),
        'retention_class' => 'project_lifecycle',
        'draft_kind' => 'product',
        'adapter' => $adapter,
        'fields' => $fields,
    ];
    $assert(RequestGuard::draft($draft));
    $draft['publish'] = true;
    $assert(!RequestGuard::draft($draft), 'A publication field crossed the draft-only boundary.');

    $metrics = [[
        'field_id' => 'campaign.click_count',
        'metric_key' => 'click_count',
        'value' => 25,
        'unit' => 'count',
        'period_start' => '2026-07-01T00:00:00Z',
        'period_end' => '2026-07-02T00:00:00Z',
        'attribution' => 'approved local aggregate',
        'aggregation_level' => 'campaign',
        'classification' => 'non_personal_aggregate',
    ]];
    $metric_payload = [
        'schema_version' => 1,
        'preview_hash' => ContractPayload::metric_preview_hash($metrics),
        'selected_field_ids' => ['campaign.click_count'],
        'consented_at' => gmdate('c'),
        'retention_class' => 'project_lifecycle',
        'metrics' => $metrics,
    ];
    $assert(RequestGuard::metrics($metric_payload));
    $metric_payload['metrics'][0]['field_id'] = 'customer.email_count';
    $assert(!RequestGuard::metrics($metric_payload), 'Personal-looking aggregate field was accepted.');
});

$test('HTTP client sends exact paths, authentication, and idempotency headers', static function () use ($assert): void {
    $connections = new ConnectionRepository();
    $GLOBALS['test_options'][ConnectionRepository::SECRET_OPTION] = ['credential' => str_repeat('c', 40)];
    $transport = new FixtureTransport([[
        'response' => ['code' => 200],
        'body' => json_encode([
            'status' => 'connected',
            'installation_id' => '0190c457-7f6b-7cc2-98f2-e041655ad101',
            'contract_version' => '1.0',
            'schema_versions' => ConnectorContract::SCHEMAS,
            'scopes' => array_values(ConnectorContract::SCOPES),
            'credential_expires_at' => '2026-10-27T12:00:00Z',
        ]),
    ]]);
    $client = new HttpClient(['api_origin' => 'https://93.184.216.34'], $connections, $transport);
    $result = $client->test_connection();
    $assert($result->success);
    $request = $transport->requests[0];
    $assert('https://93.184.216.34/api/v1/connector/installation/test' === $request['url']);
    $assert('Bearer ' . str_repeat('c', 40) === $request['arguments']['headers']['Authorization']);
    $assert(strlen($request['arguments']['headers']['Idempotency-Key']) >= 16);
    $assert(!isset($request['arguments']['body']), 'Empty test request unexpectedly sent a body.');
});

$test('pairing token is request-only and challenge schema is strict', static function () use ($assert): void {
    unset($GLOBALS['test_options'][ConnectionRepository::SECRET_OPTION]);
    $transport = new FixtureTransport([[
        'response' => ['code' => 201],
        'body' => json_encode([
            'pairing_id' => '0190c457-7f6b-7cc2-98f2-e041655ad101',
            'pairing_secret' => str_repeat('p', 40),
            'user_code' => 'ABCD-EFGH',
            'verification_path' => '/settings/connectors/pair',
            'expires_at' => '2026-07-29T12:10:00Z',
            'poll_after_seconds' => 5,
        ]),
    ]]);
    $connections = new ConnectionRepository();
    $client = new HttpClient(['api_origin' => 'https://93.184.216.34'], $connections, $transport);
    $payload = [
        'installation_key' => 'mds-companion-fixture-01',
        'project_id' => '0190c457-7f6b-7cc2-98f2-e041655ad101',
        'connector_version' => '0.2.0',
        'core_version' => '3.0.0',
        'requested_contract' => '1.0',
        'required_schemas' => ConnectorContract::SCHEMAS,
        'requested_scopes' => ConnectorContract::scopes_for_modules(['plan' => true]),
    ];
    $result = $client->begin_pairing(['platform_token' => 'temporary-platform-token', 'payload' => $payload]);
    $assert($result->success);
    $assert('Bearer temporary-platform-token' === $transport->requests[0]['arguments']['headers']['Authorization']);
    $assert(!isset($GLOBALS['test_options'][ConnectionRepository::SECRET_OPTION]), 'Platform token entered connector storage.');
    $assert(!str_contains((string) $transport->requests[0]['arguments']['body'], 'temporary-platform-token'));
});

$test('pairing completion enforces negotiated contract, schemas, and scopes', static function () use ($assert): void {
    $scopes = ConnectorContract::scopes_for_modules(['plan' => true]);
    $transport = new FixtureTransport([[
        'response' => ['code' => 200],
        'body' => json_encode([
            'installation' => [
                'installation_id' => '0190c457-7f6b-7cc2-98f2-e041655ad101',
                'organization_id' => '0190c457-7f6b-7cc2-98f2-e041655ad102',
                'project_id' => '0190c457-7f6b-7cc2-98f2-e041655ad103',
                'installation_key' => 'mds-companion-fixture-01',
                'state' => 'connected',
                'contract_version' => '1.0',
                'schema_versions' => ConnectorContract::SCHEMAS,
                'scopes' => $scopes,
                'connected_at' => '2026-07-29T12:00:00Z',
            ],
            'credential' => [
                'credential' => str_repeat('n', 40),
                'expires_at' => '2026-10-27T12:00:00Z',
            ],
        ]),
    ]]);
    $client = new HttpClient(['api_origin' => 'https://93.184.216.34'], new ConnectionRepository(), $transport);
    $result = $client->complete_pairing([
        'pairing_id' => '0190c457-7f6b-7cc2-98f2-e041655ad104',
        'pairing_secret' => str_repeat('p', 40),
        'payload' => ['requested_contract' => '1.0', 'required_schemas' => ConnectorContract::SCHEMAS],
        'required_scopes' => $scopes,
    ]);
    $assert($result->success);
    $assert(str_ends_with($transport->requests[0]['url'], '/connector/pairings/0190c457-7f6b-7cc2-98f2-e041655ad104/complete'));
    $assert('Bearer ' . str_repeat('p', 40) === $transport->requests[0]['arguments']['headers']['Authorization']);
});

$test('HTTP transfers consume the exact platform fixture', static function () use ($assert): void {
    $fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/connector-v1/compatibility.json'), true);
    $GLOBALS['test_options'][ConnectionRepository::SECRET_OPTION] = ['credential' => str_repeat('c', 40)];
    $transport = new FixtureTransport([[
        'response' => ['code' => 200],
        'body' => json_encode($fixture['transfer_result']),
    ]]);
    $client = new HttpClient(['api_origin' => 'https://93.184.216.34'], new ConnectionRepository(), $transport);
    $result = $client->send_project_context($fixture['project_context_request']);
    $assert($result->success && 'req_fixture_transfer01' === $result->request_id);
    $assert(str_ends_with($transport->requests[0]['url'], '/connector/project-context'));
    $assert($fixture['project_context_request'] === json_decode($transport->requests[0]['arguments']['body'], true));
});

$test('draft, metric, and action operations retain their narrow modes', static function () use ($assert): void {
    $fixture = json_decode((string) file_get_contents(__DIR__ . '/fixtures/connector-v1/compatibility.json'), true);
    $GLOBALS['test_options'][ConnectionRepository::SECRET_OPTION] = ['credential' => str_repeat('c', 40)];
    $adapter = [
        'adapter_key' => 'fixture-draft-adapter',
        'public_contract' => 'fixture.public.draft.v1',
        'schema_version' => 1,
        'source_object_type' => 'fixture',
    ];
    $fields = [[
        'id' => 'name',
        'source_module' => 'companion',
        'purpose' => 'Name the draft',
        'classification' => 'internal',
        'value' => 'Fixture draft',
    ]];
    $draft = [
        'schema_version' => 1,
        'preview_hash' => ContractPayload::draft_preview_hash('product', $adapter, $fields),
        'selected_field_ids' => ['name'],
        'consented_at' => gmdate('c'),
        'retention_class' => 'project_lifecycle',
        'draft_kind' => 'product',
        'adapter' => $adapter,
        'fields' => $fields,
    ];
    $metrics = [[
        'field_id' => 'campaign.click_count',
        'metric_key' => 'click_count',
        'value' => 25,
        'unit' => 'count',
        'period_start' => '2026-07-01T00:00:00Z',
        'period_end' => '2026-07-02T00:00:00Z',
        'attribution' => 'approved local aggregate',
        'aggregation_level' => 'campaign',
        'classification' => 'non_personal_aggregate',
    ]];
    $metric_payload = [
        'schema_version' => 1,
        'preview_hash' => ContractPayload::metric_preview_hash($metrics),
        'selected_field_ids' => ['campaign.click_count'],
        'consented_at' => gmdate('c'),
        'retention_class' => 'project_lifecycle',
        'metrics' => $metrics,
    ];
    $metric_result = $fixture['transfer_result'];
    $metric_result['fields'][0]['field_id'] = 'campaign.click_count';
    $transport = new FixtureTransport([
        ['response' => ['code' => 201], 'body' => json_encode($fixture['draft_result'])],
        ['response' => ['code' => 200], 'body' => json_encode($metric_result)],
        ['response' => ['code' => 200], 'body' => json_encode([
            'action_id' => '0190c457-7f6b-7cc2-98f2-e041655ad105',
            'state' => 'accepted_for_manual_review',
            'acknowledged_at' => '2026-07-29T12:00:00Z',
            'replayed' => false,
        ])],
    ]);
    $client = new HttpClient(['api_origin' => 'https://93.184.216.34'], new ConnectionRepository(), $transport);
    $assert('draft_created' === $client->create_draft('product', $draft)->status);
    $assert('accepted' === $client->send_aggregate_metrics($metric_payload)->status);
    $ack = $client->acknowledge_action('0190c457-7f6b-7cc2-98f2-e041655ad105', $fixture['acknowledgement']);
    $assert($ack->success && 'acknowledged' === $ack->status);
    $assert(str_ends_with($transport->requests[0]['url'], '/connector/drafts'));
    $assert(str_ends_with($transport->requests[1]['url'], '/connector/metrics'));
    $assert(str_ends_with($transport->requests[2]['url'], '/connector/actions/0190c457-7f6b-7cc2-98f2-e041655ad105/acknowledge'));
    $assert(!array_key_exists('publish', json_decode($transport->requests[0]['arguments']['body'], true)));
});

$test('HTTP client retries bounded retryable requests and not credential rotation', static function () use ($assert): void {
    $GLOBALS['test_options'][ConnectionRepository::SECRET_OPTION] = ['credential' => str_repeat('c', 40)];
    $success = [
        'response' => ['code' => 200],
        'body' => json_encode([
            'status' => 'connected',
            'installation_id' => '0190c457-7f6b-7cc2-98f2-e041655ad101',
            'contract_version' => '1.0',
            'schema_versions' => ConnectorContract::SCHEMAS,
            'scopes' => [],
            'credential_expires_at' => '2026-10-27T12:00:00Z',
        ]),
    ];
    $retry_transport = new FixtureTransport([
        new WP_Error('timeout', 'timeout'),
        new WP_Error('timeout', 'timeout'),
        $success,
    ]);
    $client = new HttpClient(['api_origin' => 'https://93.184.216.34'], new ConnectionRepository(), $retry_transport);
    $assert($client->test_connection()->success);
    $assert(3 === count($retry_transport->requests));
    $assert(1 === count(array_unique(array_map(
        static fn(array $request): string => $request['arguments']['headers']['Idempotency-Key'],
        $retry_transport->requests
    ))), 'A retry changed the idempotency key.');

    $rotation_transport = new FixtureTransport([
        new WP_Error('timeout', 'timeout'),
        ['response' => ['code' => 200], 'body' => '{"credential":"' . str_repeat('n', 40) . '","expires_at":"2026-10-27T12:00:00Z"}'],
    ]);
    $rotation = new HttpClient(['api_origin' => 'https://93.184.216.34'], new ConnectionRepository(), $rotation_transport);
    $assert(!$rotation->rotate_credential()->success);
    $assert(1 === count($rotation_transport->requests));
});

$test('revocation errors clear connector credentials without exposing response bodies', static function () use ($assert): void {
    $GLOBALS['test_options'][ConnectionRepository::SECRET_OPTION] = ['credential' => str_repeat('c', 40)];
    $transport = new FixtureTransport([[
        'response' => ['code' => 410],
        'body' => json_encode([
            'type' => 'https://platform.example/problems/installation-revoked',
            'title' => 'Installation revoked',
            'status' => 410,
            'detail' => 'This connector installation was revoked.',
            'request_id' => 'req_fixture_revoked01',
            'code' => 'installation_revoked',
            'retryable' => false,
        ]),
    ]]);
    $connections = new ConnectionRepository();
    $client = new HttpClient(['api_origin' => 'https://93.184.216.34'], $connections, $transport);
    $result = $client->test_connection();
    $assert(!$result->success && 'installation_revoked' === $result->error_code);
    $assert('' === $connections->credential());
});

$test('negotiated schema mismatch disconnects safely', static function () use ($assert): void {
    $GLOBALS['test_options'][ConnectionRepository::SECRET_OPTION] = ['credential' => str_repeat('c', 40)];
    $transport = new FixtureTransport([[
        'response' => ['code' => 200],
        'body' => json_encode([
            'status' => 'connected',
            'installation_id' => '0190c457-7f6b-7cc2-98f2-e041655ad101',
            'contract_version' => '1.0',
            'schema_versions' => array_merge(ConnectorContract::SCHEMAS, ['project_context' => 2]),
            'scopes' => [],
            'credential_expires_at' => '2026-10-27T12:00:00Z',
        ]),
    ]]);
    $connections = new ConnectionRepository();
    $client = new HttpClient(['api_origin' => 'https://93.184.216.34'], $connections, $transport);
    $result = $client->test_connection();
    $assert(!$result->success && 'schema_mismatch' === $result->error_code);
    $assert('' === $connections->credential());
});

$test('registry rejects missing public contracts', static function () use ($assert): void {
    $adapter = new class implements ExportAdapter {
        public function object_type(): string { return 'private_object'; }
        public function owner(): string { return 'other-extension'; }
        public function available(): bool { return true; }
        public function required_public_contract(): string { return 'missing.contract'; }
        public function required_capability(): string { return 'manage_options'; }
        public function schema_version(): int { return 1; }
        public function list_items(): array { return []; }
        public function preview(string $id, array $selected_fields): array { return []; }
        public function validate(array $payload): bool { return false; }
    };
    $registry = new AdapterRegistry();
    $assert(!$registry->register($adapter));
    $assert($registry->register(new ProjectContextAdapter()));
});

$failures = array_values(array_filter($tests, static fn(array $result): bool => !$result['ok']));
foreach ($tests as $result) {
    echo ($result['ok'] ? 'PASS' : 'FAIL') . ' - ' . $result['name'];
    if (!$result['ok']) {
        echo ': ' . $result['message'];
    }
    echo PHP_EOL;
}
exit($failures ? 1 : 0);
