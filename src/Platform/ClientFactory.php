<?php
/**
 * Platform client selection boundary.
 *
 * @package MillionDollarScript\Extensions\ProjectCompanion
 */

namespace MillionDollarScript\Extensions\ProjectCompanion\Platform;

use MillionDollarScript\Extensions\ProjectCompanion\Platform\Http\HttpClient;
use MillionDollarScript\Extensions\ProjectCompanion\Platform\Http\WordPressTransport;

if (!defined('ABSPATH')) {
    exit;
}

final class ClientFactory {
    public static function make(): PlatformClient {
        $client = apply_filters('million-dollar-script/project-companion/platform-client', null);
        if ($client instanceof PlatformClient) {
            return $client;
        }
        $config = HostedServiceConfig::get();
        if (!$config['valid']) {
            return new DisabledClient();
        }
        return new HttpClient(
            $config,
            new ConnectionRepository(),
            new WordPressTransport($config['local_development'])
        );
    }
}
