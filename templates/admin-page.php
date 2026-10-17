<?php
/**
 * Project Companion administration screen.
 *
 * @var string                                                       $tab
 * @var string                                                       $filter
 * @var array<string,mixed>                                          $settings
 * @var array<string,mixed>                                          $snapshot
 * @var array<int,array<string,mixed>>                               $recommendations
 * @var array<string,mixed>                                          $preview
 * @var array<int,array<string,mixed>>                               $activity
 * @var array{display_name:string,api_origin:string,app_url:string,docs_url:string,local_development:bool,valid:bool} $hosted
 * @var \MillionDollarScript\Extensions\ProjectCompanion\Platform\ClientResult $connection
 * @var array<string,array<string,mixed>>                             $fields
 * @var array<string,mixed>                                          $pairing
 * @var array<int,string>                                            $required_scopes
 * @var array<int,string>                                            $missing_scopes
 * @var array<string,array<string,mixed>>                             $hosted_views
 */

if (!defined('ABSPATH')) {
    exit;
}

$tabs = [
    'overview' => __('Overview', 'mds-project-companion'),
    'connection' => __('Connection', 'mds-project-companion'),
    'modules' => __('Modules', 'mds-project-companion'),
    'recommendations' => __('Recommendations', 'mds-project-companion'),
    'shared-data' => __('Shared Data', 'mds-project-companion'),
    'activity' => __('Activity', 'mds-project-companion'),
    'diagnostics' => __('Diagnostics', 'mds-project-companion'),
    'documentation' => __('Documentation', 'mds-project-companion'),
];
$status = sanitize_key((string) ($_GET['status'] ?? ''));
?>
<div class="wrap mds3-admin mds-project-companion">
    <div class="mds-project-companion__heading">
        <div>
            <h1><?php esc_html_e('Project Companion', 'mds-project-companion'); ?></h1>
            <p><?php esc_html_e('Plan locally, review transparent extension guidance, and control exactly what could be shared in the future.', 'mds-project-companion'); ?></p>
        </div>
        <span class="mds-project-companion__local-badge"><?php echo esc_html($hosted['valid'] ? __('Local + connector v1', 'mds-project-companion') : __('Local mode', 'mds-project-companion')); ?></span>
    </div>
    <hr class="wp-header-end">
    <?php if ($status) : ?>
        <div class="notice <?php echo in_array($status, ['refresh-failed', 'consent-failed', 'clear-failed'], true) ? 'notice-error' : 'notice-success'; ?> is-dismissible">
            <p><?php echo esc_html(sprintf(__('Project Companion status: %s.', 'mds-project-companion'), str_replace('-', ' ', $status))); ?></p>
        </div>
    <?php endif; ?>
    <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Project Companion sections', 'mds-project-companion'); ?>">
        <?php foreach ($tabs as $key => $label) : ?>
            <a class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['page' => 'mds-project-companion', 'tab' => $key], admin_url('admin.php'))); ?>" <?php echo $tab === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ('overview' === $tab) : ?>
        <div class="mds-project-companion__grid">
            <section class="mds3-card">
                <h2><?php esc_html_e('Project profile', 'mds-project-companion'); ?></h2>
                <p><?php esc_html_e('These optional local details improve recommendations. They are not sent anywhere.', 'mds-project-companion'); ?></p>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                    <input type="hidden" name="action" value="mds_project_companion_save">
                    <input type="hidden" name="return_tab" value="overview">
                    <input type="hidden" name="setup_completed" value="1">
                    <?php wp_nonce_field('mds_project_companion_save'); ?>
                    <label class="mds-project-companion__field"><span><?php esc_html_e('Project name', 'mds-project-companion'); ?></span><input type="text" name="settings[profile][name]" maxlength="160" value="<?php echo esc_attr((string) $settings['profile']['name']); ?>"></label>
                    <label class="mds-project-companion__field"><span><?php esc_html_e('Summary', 'mds-project-companion'); ?></span><textarea name="settings[profile][summary]" maxlength="2000" rows="5"><?php echo esc_textarea((string) $settings['profile']['summary']); ?></textarea></label>
                    <fieldset><legend><?php esc_html_e('Goals', 'mds-project-companion'); ?></legend><div class="mds-project-companion__checks">
                        <?php foreach (['funding', 'local', 'sponsorship', 'recurring', 'launches', 'missions', 'bounties', 'tools', 'distribution', 'analytics', 'growth'] as $goal) : ?>
                            <label><input type="checkbox" name="settings[profile][goals][]" value="<?php echo esc_attr($goal); ?>" <?php checked(in_array($goal, $settings['profile']['goals'], true)); ?>> <?php echo esc_html(ucwords(str_replace('-', ' ', $goal))); ?></label>
                        <?php endforeach; ?>
                    </div></fieldset>
                    <div class="mds-project-companion__three">
                        <?php foreach ([
                            'focus' => ['unspecified', 'local', 'online', 'hybrid'],
                            'funding' => ['unspecified', 'none', 'one-time', 'recurring', 'both'],
                            'external_services' => ['undecided', 'yes', 'no'],
                            'setup_effort' => ['simple', 'moderate', 'advanced'],
                        ] as $key => $choices) : ?>
                            <label class="mds-project-companion__field"><span><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></span><select name="settings[profile][<?php echo esc_attr($key); ?>]">
                                <?php foreach ($choices as $choice) : ?><option value="<?php echo esc_attr($choice); ?>" <?php selected($settings['profile'][$key], $choice); ?>><?php echo esc_html(ucwords(str_replace('-', ' ', $choice))); ?></option><?php endforeach; ?>
                            </select></label>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ($settings['modules'] as $module => $enabled) : ?><input type="hidden" name="settings[modules][<?php echo esc_attr($module); ?>]" value="<?php echo $enabled ? '1' : '0'; ?>"><?php endforeach; ?>
                    <input type="hidden" name="settings[retention_days]" value="<?php echo esc_attr(0 === (int) $settings['retention_days'] ? 'indefinite' : (string) $settings['retention_days']); ?>">
                    <?php foreach ($settings['sharing'] as $id => $enabled) : ?><input type="hidden" name="settings[sharing][<?php echo esc_attr($id); ?>]" value="<?php echo $enabled ? '1' : '0'; ?>"><?php endforeach; ?>
                    <?php submit_button(__('Save local profile', 'mds-project-companion')); ?>
                </form>
            </section>
            <section class="mds3-card">
                <h2><?php esc_html_e('Setup checklist', 'mds-project-companion'); ?></h2>
                <ol><?php foreach (\MillionDollarScript\Extensions\ProjectCompanion\Recommendations\Engine::checklist($recommendations) as $item) : ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?></ol>
                <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds-project-companion&tab=recommendations')); ?>"><?php esc_html_e('Review recommendations', 'mds-project-companion'); ?></a></p>
            </section>
        </div>
    <?php elseif ('connection' === $tab) : ?>
        <section class="mds3-card mds-project-companion__narrow">
            <h2><?php echo esc_html($hosted['display_name']); ?></h2>
            <div class="mds-project-companion__state <?php echo $connection->success ? 'mds-project-companion__state--connected' : 'mds-project-companion__state--disabled'; ?>"><strong><?php echo esc_html($connection->success ? __('Connected', 'mds-project-companion') : __('Disconnected', 'mds-project-companion')); ?></strong><span><?php echo esc_html($connection->message); ?></span></div>
            <p><?php esc_html_e('Normal administration page loads make no hosted request.', 'mds-project-companion'); ?></p>
            <?php if (!$hosted['valid']) : ?>
                <p><?php esc_html_e('A valid hosted API origin has not been configured. Local recommendations, capability discovery, previews, consent controls, and audit history remain available.', 'mds-project-companion'); ?></p>
                <p class="description"><?php esc_html_e('Site operators configure the brand-neutral API origin and optional display links through the documented hosted-service configuration filter. Production origins must use HTTPS.', 'mds-project-companion'); ?></p>
            <?php else : ?>
                <dl class="mds-project-companion__diagnostics">
                    <div><dt><?php esc_html_e('Contract', 'mds-project-companion'); ?></dt><dd><?php echo esc_html(\MillionDollarScript\Extensions\ProjectCompanion\Platform\ConnectorContract::VERSION); ?></dd></div>
                    <div><dt><?php esc_html_e('API major', 'mds-project-companion'); ?></dt><dd><?php echo esc_html(\MillionDollarScript\Extensions\ProjectCompanion\Platform\ConnectorContract::API_MAJOR); ?></dd></div>
                    <div><dt><?php esc_html_e('Configured origin', 'mds-project-companion'); ?></dt><dd><code><?php echo esc_html($hosted['api_origin']); ?></code></dd></div>
                    <div><dt><?php esc_html_e('Credential expiry', 'mds-project-companion'); ?></dt><dd><?php echo esc_html((string) ($connection->data['credential_expires_at'] ?? __('Not connected', 'mds-project-companion'))); ?></dd></div>
                </dl>
                <details><summary><?php esc_html_e('Scopes required by enabled modules', 'mds-project-companion'); ?></summary><ul><?php foreach ($required_scopes as $scope) : ?><li><code><?php echo esc_html($scope); ?></code></li><?php endforeach; ?></ul></details>
                <?php if ($connection->success && $missing_scopes) : ?><div class="notice notice-warning inline"><p><?php esc_html_e('Enabled modules require scopes that were not granted during pairing. Revoke and pair again to negotiate the updated least-privilege scope set.', 'mds-project-companion'); ?></p></div><?php endif; ?>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                    <input type="hidden" name="action" value="mds_project_companion_compatibility"><?php wp_nonce_field('mds_project_companion_compatibility'); ?>
                    <?php submit_button(__('Check contract compatibility', 'mds-project-companion'), 'secondary'); ?>
                </form>
                <?php $compatibility = is_array($connection->data['compatibility'] ?? null) ? $connection->data['compatibility'] : []; ?>
                <?php if ($compatibility) : ?><div class="notice <?php echo !empty($compatibility['compatible']) ? 'notice-success' : 'notice-error'; ?> inline"><p><strong><?php echo esc_html(!empty($compatibility['compatible']) ? __('Compatible', 'mds-project-companion') : __('Incompatible', 'mds-project-companion')); ?></strong><?php if (!empty($compatibility['checked_at'])) : ?>: <?php echo esc_html((string) $compatibility['checked_at']); ?><?php endif; ?></p><?php if (!empty($compatibility['reasons'])) : ?><ul><?php foreach ((array) $compatibility['reasons'] as $reason) : ?><li><code><?php echo esc_html((string) $reason); ?></code></li><?php endforeach; ?></ul><?php endif; ?></div><?php endif; ?>

                <?php if (!$connection->success) : ?>
                    <hr>
                    <h3><?php esc_html_e('Pair this site', 'mds-project-companion'); ?></h3>
                    <p><?php esc_html_e('Pairing is an explicit administrator action. The temporary platform token is used for this request only and is never saved.', 'mds-project-companion'); ?></p>
                    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" autocomplete="off">
                        <input type="hidden" name="action" value="mds_project_companion_begin_pairing"><?php wp_nonce_field('mds_project_companion_begin_pairing'); ?>
                        <label class="mds-project-companion__field"><span><?php esc_html_e('Hosted project ID', 'mds-project-companion'); ?></span><input type="text" name="project_id" required pattern="[0-9a-fA-F-]{36}" autocomplete="off"></label>
                        <label class="mds-project-companion__field"><span><?php esc_html_e('Temporary authenticated platform token', 'mds-project-companion'); ?></span><input type="password" name="platform_token" required autocomplete="new-password"></label>
                        <?php submit_button(__('Begin pairing', 'mds-project-companion')); ?>
                    </form>
                    <?php if ($pairing) : ?>
                        <div class="mds-project-companion__preview">
                            <h3><?php esc_html_e('Approve the pairing in the hosted platform', 'mds-project-companion'); ?></h3>
                            <p><strong><?php esc_html_e('Code:', 'mds-project-companion'); ?></strong> <code><?php echo esc_html((string) $pairing['user_code']); ?></code></p>
                            <p><?php echo esc_html(sprintf(__('This challenge expires at %s.', 'mds-project-companion'), (string) $pairing['expires_at'])); ?></p>
                            <?php if ($hosted['app_url']) : ?><p><a class="button" href="<?php echo esc_url(rtrim($hosted['app_url'], '/') . (string) $pairing['verification_path']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open hosted approval page', 'mds-project-companion'); ?></a></p><?php endif; ?>
                            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                                <input type="hidden" name="action" value="mds_project_companion_complete_pairing"><?php wp_nonce_field('mds_project_companion_complete_pairing'); ?>
                                <?php submit_button(__('Complete approved pairing', 'mds-project-companion')); ?>
                            </form>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <hr>
                    <div class="mds-project-companion__actions">
                        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="mds_project_companion_test_connection"><?php wp_nonce_field('mds_project_companion_test_connection'); ?><button class="button" type="submit"><?php esc_html_e('Test connection', 'mds-project-companion'); ?></button></form>
                        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-confirm="<?php esc_attr_e('Rotate the connector credential now?', 'mds-project-companion'); ?>"><input type="hidden" name="action" value="mds_project_companion_rotate"><?php wp_nonce_field('mds_project_companion_rotate'); ?><button class="button" type="submit"><?php esc_html_e('Rotate credential', 'mds-project-companion'); ?></button></form>
                        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-confirm="<?php esc_attr_e('Revoke this connector installation?', 'mds-project-companion'); ?>"><input type="hidden" name="action" value="mds_project_companion_revoke"><?php wp_nonce_field('mds_project_companion_revoke'); ?><button class="button button-link-delete" type="submit"><?php esc_html_e('Revoke connection', 'mds-project-companion'); ?></button></form>
                    </div>
                    <hr>
                    <h3><?php esc_html_e('Explicit hosted reads', 'mds-project-companion'); ?></h3>
                    <p><?php esc_html_e('Each button makes one administrator-initiated read and stores only its connector-safe view locally for display.', 'mds-project-companion'); ?></p>
                    <div class="mds-project-companion__actions">
                        <?php foreach ([
                            'plan' => ['plan', __('Fetch plan summary', 'mds-project-companion')],
                            'recommendations' => ['plan', __('Fetch hosted recommendations', 'mds-project-companion')],
                            'create' => ['create', __('Fetch Create journey', 'mds-project-companion')],
                            'distribution' => ['distribute', __('Fetch distribution summary', 'mds-project-companion')],
                            'growth' => ['grow', __('Fetch growth summary', 'mds-project-companion')],
                        ] as $view => [$module, $label]) : ?>
                            <?php if (!empty($settings['modules'][$module])) : ?><form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="mds_project_companion_fetch_view"><input type="hidden" name="view" value="<?php echo esc_attr($view); ?>"><?php wp_nonce_field('mds_project_companion_fetch_view'); ?><button class="button" type="submit"><?php echo esc_html($label); ?></button></form><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ($hosted_views as $view => $cached) : if (!$cached) { continue; } ?><details><summary><?php echo esc_html(sprintf(__('%s view, fetched %s', 'mds-project-companion'), ucfirst($view), (string) ($cached['fetched_at'] ?? ''))); ?></summary><pre><?php echo esc_html((string) wp_json_encode($cached['data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></details><?php endforeach; ?>
                    <?php if (in_array(\MillionDollarScript\Extensions\ProjectCompanion\Platform\ConnectorContract::SCOPES['action_acknowledge'], (array) ($connection->data['scopes'] ?? []), true)) : ?>
                        <hr>
                        <h3><?php esc_html_e('Acknowledge a hosted action', 'mds-project-companion'); ?></h3>
                        <p><?php esc_html_e('Acknowledgement only records your response in the hosted platform. It never runs an action or changes this site.', 'mds-project-companion'); ?></p>
                        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                            <input type="hidden" name="action" value="mds_project_companion_acknowledge"><?php wp_nonce_field('mds_project_companion_acknowledge'); ?>
                            <label class="mds-project-companion__field"><span><?php esc_html_e('Action ID', 'mds-project-companion'); ?></span><input type="text" name="action_id" required pattern="[0-9a-fA-F-]{36}"></label>
                            <label class="mds-project-companion__field"><span><?php esc_html_e('Response', 'mds-project-companion'); ?></span><select name="acknowledgement_state"><option value="seen"><?php esc_html_e('Seen', 'mds-project-companion'); ?></option><option value="accepted_for_manual_review"><?php esc_html_e('Accepted for manual review', 'mds-project-companion'); ?></option><option value="dismissed"><?php esc_html_e('Dismissed', 'mds-project-companion'); ?></option></select></label>
                            <label class="mds-project-companion__field"><span><?php esc_html_e('Optional note', 'mds-project-companion'); ?></span><textarea name="note" maxlength="500" rows="3"></textarea></label>
                            <?php submit_button(__('Send acknowledgement', 'mds-project-companion'), 'secondary'); ?>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    <?php elseif ('modules' === $tab) : ?>
        <section class="mds3-card">
            <h2><?php esc_html_e('Modules', 'mds-project-companion'); ?></h2>
            <p><?php esc_html_e('Modules are independent and disabled by default. Enabling one only reveals its local controls; it does not send data.', 'mds-project-companion'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="mds_project_companion_save"><input type="hidden" name="return_tab" value="modules"><?php wp_nonce_field('mds_project_companion_save'); ?>
                <div class="mds-project-companion__module-grid">
                    <?php foreach ([
                        'plan' => __('Project context, plans, recommendations, and setup guidance.', 'mds-project-companion'),
                        'create' => __('MDS capability context and future learning-track milestones.', 'mds-project-companion'),
                        'distribute' => __('Future approved draft exports through documented adapters.', 'mds-project-companion'),
                        'grow' => __('Future approved aggregate metrics and experiment findings.', 'mds-project-companion'),
                    ] as $module => $description) : ?>
                        <label class="mds-project-companion__module"><span><strong><?php echo esc_html(ucfirst($module)); ?></strong><small><?php echo esc_html($description); ?></small></span><input type="checkbox" name="settings[modules][<?php echo esc_attr($module); ?>]" value="1" <?php checked($settings['modules'][$module]); ?>></label>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($settings['profile'] as $key => $value) : if (is_array($value)) { foreach ($value as $item) { echo '<input type="hidden" name="settings[profile][' . esc_attr($key) . '][]" value="' . esc_attr($item) . '">'; } } else { echo '<input type="hidden" name="settings[profile][' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">'; } endforeach; ?>
                <input type="hidden" name="settings[retention_days]" value="<?php echo esc_attr(0 === (int) $settings['retention_days'] ? 'indefinite' : (string) $settings['retention_days']); ?>">
                <?php foreach ($settings['sharing'] as $id => $enabled) : ?><input type="hidden" name="settings[sharing][<?php echo esc_attr($id); ?>]" value="<?php echo $enabled ? '1' : '0'; ?>"><?php endforeach; ?>
                <?php submit_button(__('Save modules', 'mds-project-companion')); ?>
            </form>
        </section>
    <?php elseif ('recommendations' === $tab) : ?>
        <section class="mds3-card">
            <div class="mds-project-companion__section-heading"><div><h2><?php esc_html_e('Extension recommendations', 'mds-project-companion'); ?></h2><p><?php esc_html_e('Local deterministic guidance. Paid products receive no ranking advantage.', 'mds-project-companion'); ?></p></div>
                <form method="get"><input type="hidden" name="page" value="mds-project-companion"><input type="hidden" name="tab" value="recommendations"><label><span class="screen-reader-text"><?php esc_html_e('Recommendation filter', 'mds-project-companion'); ?></span><select name="recommendation_filter" onchange="this.form.submit()"><?php foreach (['best-fit' => __('Best fit', 'mds-project-companion'), 'installed' => __('Installed only', 'mds-project-companion'), 'free' => __('Free only', 'mds-project-companion'), 'no-services' => __('No external services', 'mds-project-companion')] as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>" <?php selected($filter, $key); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label></form>
            </div>
            <div class="mds-project-companion__recommendations">
                <?php foreach ($recommendations as $item) : ?>
                    <article class="mds-project-companion__recommendation">
                        <div class="mds-project-companion__section-heading"><h3><?php echo esc_html((string) $item['name']); ?></h3><span class="mds-project-companion__pill"><?php echo esc_html(ucfirst((string) $item['commercial'])); ?></span></div>
                        <p><?php echo esc_html((string) $item['summary']); ?></p><p><strong><?php esc_html_e('Why it may fit:', 'mds-project-companion'); ?></strong> <?php echo esc_html((string) $item['why']); ?></p>
                        <dl><div><dt><?php esc_html_e('State', 'mds-project-companion'); ?></dt><dd><?php echo esc_html($item['active'] ? __('Active', 'mds-project-companion') : ($item['installed'] ? __('Installed, inactive', 'mds-project-companion') : (true === $item['available'] ? __('Available', 'mds-project-companion') : __('Availability unknown', 'mds-project-companion')))); ?></dd></div><div><dt><?php esc_html_e('External service', 'mds-project-companion'); ?></dt><dd><?php echo esc_html($item['requires_service'] ? __('Required', 'mds-project-companion') : __('Not required', 'mds-project-companion')); ?></dd></div><div><dt><?php esc_html_e('Setup effort', 'mds-project-companion'); ?></dt><dd><?php echo esc_html(ucfirst((string) $item['setup_effort'])); ?></dd></div><div><dt><?php esc_html_e('Dependencies', 'mds-project-companion'); ?></dt><dd><?php echo esc_html($item['dependencies'] ? implode(', ', $item['dependencies']) : __('None reported', 'mds-project-companion')); ?></dd></div></dl>
                        <p><strong><?php esc_html_e('Alternatives:', 'mds-project-companion'); ?></strong> <?php echo esc_html($item['alternatives'] ? implode(', ', $item['alternatives']) : __('Use a manual workflow or another public MDS extension.', 'mds-project-companion')); ?></p>
                        <p><strong><?php esc_html_e('Without it:', 'mds-project-companion'); ?></strong> <?php echo esc_html((string) $item['without_it']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
            <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=mds3-extensions')); ?>"><?php esc_html_e('Open MDS Extensions', 'mds-project-companion'); ?></a></p>
        </section>
    <?php elseif ('shared-data' === $tab) : ?>
        <section class="mds3-card">
            <h2><?php esc_html_e('Data-sharing controls', 'mds-project-companion'); ?></h2>
            <p><?php esc_html_e('Nothing leaves this site merely because a module or field is enabled. Every hosted transfer requires a fresh exact preview and explicit approval.', 'mds-project-companion'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="mds_project_companion_save"><input type="hidden" name="return_tab" value="shared-data"><?php wp_nonce_field('mds_project_companion_save'); ?>
                <table class="widefat striped"><thead><tr><th><?php esc_html_e('Field', 'mds-project-companion'); ?></th><th><?php esc_html_e('Module and purpose', 'mds-project-companion'); ?></th><th><?php esc_html_e('Personal data risk', 'mds-project-companion'); ?></th><th><?php esc_html_e('Requirement', 'mds-project-companion'); ?></th><th><?php esc_html_e('Last transfer', 'mds-project-companion'); ?></th><th><?php esc_html_e('Enabled', 'mds-project-companion'); ?></th></tr></thead><tbody>
                    <?php foreach ($fields as $id => $field) : ?><tr><th scope="row"><code><?php echo esc_html($id); ?></code></th><td><strong><?php echo esc_html(ucfirst((string) $field['module'])); ?></strong><br><?php echo esc_html((string) $field['purpose']); ?><br><small><?php echo esc_html((string) $field['known_retention']); ?></small></td><td><?php echo esc_html($field['could_contain_personal_data'] ? __('Possible', 'mds-project-companion') : __('Not expected', 'mds-project-companion')); ?></td><td><?php echo esc_html($field['required'] ? __('Required for this transfer type', 'mds-project-companion') : __('Optional', 'mds-project-companion')); ?></td><td><?php echo esc_html((string) ($connection->data['last_transfers'][$id]['transferred_at'] ?? __('Never', 'mds-project-companion'))); ?></td><td><input aria-label="<?php echo esc_attr(sprintf(__('Enable %s for previews', 'mds-project-companion'), $id)); ?>" type="checkbox" name="settings[sharing][<?php echo esc_attr($id); ?>]" value="1" <?php checked(!empty($settings['sharing'][$id])); ?>></td></tr><?php endforeach; ?>
                </tbody></table>
                <?php foreach ($settings['modules'] as $module => $enabled) : ?><input type="hidden" name="settings[modules][<?php echo esc_attr($module); ?>]" value="<?php echo $enabled ? '1' : '0'; ?>"><?php endforeach; ?>
                <?php foreach ($settings['profile'] as $key => $value) : if (is_array($value)) { foreach ($value as $item) { echo '<input type="hidden" name="settings[profile][' . esc_attr($key) . '][]" value="' . esc_attr($item) . '">'; } } else { echo '<input type="hidden" name="settings[profile][' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">'; } endforeach; ?>
                <input type="hidden" name="settings[retention_days]" value="<?php echo esc_attr(0 === (int) $settings['retention_days'] ? 'indefinite' : (string) $settings['retention_days']); ?>">
                <?php submit_button(__('Save sharing preferences', 'mds-project-companion')); ?>
            </form>
            <hr>
            <h3><?php esc_html_e('Create an exact preview', 'mds-project-companion'); ?></h3>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="mds_project_companion_preview"><?php wp_nonce_field('mds_project_companion_preview'); ?>
                <label><?php esc_html_e('Module', 'mds-project-companion'); ?> <select name="module"><?php foreach (['plan', 'create'] as $module) : ?><option value="<?php echo esc_attr($module); ?>" <?php disabled(empty($settings['modules'][$module])); ?>><?php echo esc_html(ucfirst($module)); ?></option><?php endforeach; ?></select></label>
                <div class="mds-project-companion__checks"><?php foreach ($fields as $id => $field) : ?><label><input type="checkbox" name="field_ids[]" value="<?php echo esc_attr($id); ?>" <?php disabled(empty($settings['sharing'][$id])); ?>> <?php echo esc_html(ucwords(str_replace('_', ' ', $id))); ?></label><?php endforeach; ?></div>
                <?php submit_button(__('Preview selected fields', 'mds-project-companion'), 'secondary'); ?>
            </form>
            <?php if ($preview) : ?><div class="mds-project-companion__preview"><h3><?php esc_html_e('Pending preview', 'mds-project-companion'); ?></h3><pre><?php echo esc_html((string) wp_json_encode('capability_snapshot' === ($preview['transfer_kind'] ?? '') ? ($preview['snapshot'] ?? []) : ($preview['fields'] ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre><form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="mds_project_companion_approve"><input type="hidden" name="preview_hash" value="<?php echo esc_attr((string) $preview['hash']); ?>"><?php wp_nonce_field('mds_project_companion_approve'); ?><label><input type="checkbox" name="explicit_approval" value="1" required> <?php esc_html_e('I approve this exact preview for this one hosted transfer.', 'mds-project-companion'); ?></label><?php submit_button(__('Approve transfer', 'mds-project-companion'), 'primary'); ?></form><p class="description"><?php echo esc_html($hosted['valid'] && $connection->success ? __('Approval sends only this hash-bound preview.', 'mds-project-companion') : __('No data will leave the site while the hosted client is disconnected.', 'mds-project-companion')); ?></p></div><?php endif; ?>
            <p class="description"><?php esc_html_e('Draft exports and aggregate-metric transfers remain unavailable until an owning extension exposes an approved public adapter or aggregate source contract. Hosted draft methods are draft-only; no publishing method exists.', 'mds-project-companion'); ?></p>
            <hr><h3><?php esc_html_e('Owned data controls', 'mds-project-companion'); ?></h3><div class="mds-project-companion__actions">
                <?php foreach (['export' => __('Export companion data', 'mds-project-companion'), 'cache' => __('Clear discovery cache', 'mds-project-companion'), 'profile' => __('Clear project profile', 'mds-project-companion'), 'audit' => __('Clear audit history', 'mds-project-companion')] as $target => $label) : ?><form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" <?php echo 'audit' === $target ? 'data-confirm="' . esc_attr__('Clear the complete local audit history?', 'mds-project-companion') . '"' : ''; ?>><input type="hidden" name="action" value="mds_project_companion_clear"><input type="hidden" name="target" value="<?php echo esc_attr($target); ?>"><?php wp_nonce_field('mds_project_companion_clear'); ?><button class="button<?php echo in_array($target, ['profile', 'audit'], true) ? ' button-link-delete' : ''; ?>" type="submit"><?php echo esc_html($label); ?></button></form><?php endforeach; ?>
            </div>
        </section>
    <?php elseif ('activity' === $tab) : ?>
        <section class="mds3-card"><h2><?php esc_html_e('Redacted local activity', 'mds-project-companion'); ?></h2><p><?php esc_html_e('Secrets, raw payloads, response bodies, authorization headers, and customer data are never intentionally recorded.', 'mds-project-companion'); ?></p>
            <?php if (!$activity) : ?><p><?php esc_html_e('No activity has been recorded.', 'mds-project-companion'); ?></p><?php else : ?><table class="widefat striped"><thead><tr><th><?php esc_html_e('Time (UTC)', 'mds-project-companion'); ?></th><th><?php esc_html_e('Event', 'mds-project-companion'); ?></th><th><?php esc_html_e('Module', 'mds-project-companion'); ?></th><th><?php esc_html_e('Outcome', 'mds-project-companion'); ?></th><th><?php esc_html_e('Request ID', 'mds-project-companion'); ?></th></tr></thead><tbody><?php foreach ($activity as $event) : ?><tr><td><?php echo esc_html((string) $event['created_at']); ?></td><td><?php echo esc_html((string) $event['event_type']); ?></td><td><?php echo esc_html((string) $event['module']); ?></td><td><?php echo esc_html((string) $event['outcome']); ?></td><td><code><?php echo esc_html((string) $event['request_id']); ?></code></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
        </section>
    <?php elseif ('diagnostics' === $tab) : ?>
        <section class="mds3-card"><div class="mds-project-companion__section-heading"><div><h2><?php esc_html_e('Diagnostics', 'mds-project-companion'); ?></h2><p><?php esc_html_e('Local runtime state and the last explicit catalogue refresh.', 'mds-project-companion'); ?></p></div><form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="mds_project_companion_refresh"><?php wp_nonce_field('mds_project_companion_refresh'); ?><button class="button" type="submit"><?php esc_html_e('Refresh public MDS discovery', 'mds-project-companion'); ?></button></form></div>
            <dl class="mds-project-companion__diagnostics"><div><dt><?php esc_html_e('Core version', 'mds-project-companion'); ?></dt><dd><?php echo esc_html((string) $snapshot['core']['version']); ?></dd></div><div><dt><?php esc_html_e('Extension API', 'mds-project-companion'); ?></dt><dd><?php echo esc_html((string) $snapshot['core']['extension_api_version']); ?></dd></div><div><dt><?php esc_html_e('Snapshot schema', 'mds-project-companion'); ?></dt><dd><?php echo esc_html((string) $snapshot['schema_version']); ?></dd></div><div><dt><?php esc_html_e('Available catalogue source', 'mds-project-companion'); ?></dt><dd><?php echo esc_html((string) $snapshot['source']['available_catalog']); ?></dd></div><div><dt><?php esc_html_e('Core uninstall parent', 'mds-project-companion'); ?></dt><dd><?php echo esc_html(class_exists('\MillionDollarScript\Core\Settings') ? (string) \MillionDollarScript\Core\Settings::get('delete_data_on_uninstall', 'no') : __('Unavailable; data will be preserved', 'mds-project-companion')); ?></dd></div></dl>
            <details><summary><?php esc_html_e('Capability snapshot JSON', 'mds-project-companion'); ?></summary><pre><?php echo esc_html((string) wp_json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></details>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"><input type="hidden" name="action" value="mds_project_companion_save"><input type="hidden" name="return_tab" value="diagnostics"><?php wp_nonce_field('mds_project_companion_save'); ?>
                <?php foreach ($settings['modules'] as $module => $enabled) : ?><input type="hidden" name="settings[modules][<?php echo esc_attr($module); ?>]" value="<?php echo $enabled ? '1' : '0'; ?>"><?php endforeach; ?><?php foreach ($settings['profile'] as $key => $value) : if (is_array($value)) { foreach ($value as $item) { echo '<input type="hidden" name="settings[profile][' . esc_attr($key) . '][]" value="' . esc_attr($item) . '">'; } } else { echo '<input type="hidden" name="settings[profile][' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">'; } endforeach; ?><?php foreach ($settings['sharing'] as $id => $enabled) : ?><input type="hidden" name="settings[sharing][<?php echo esc_attr($id); ?>]" value="<?php echo $enabled ? '1' : '0'; ?>"><?php endforeach; ?>
                <label class="mds-project-companion__field"><span><?php esc_html_e('Audit retention', 'mds-project-companion'); ?></span><select name="settings[retention_days]"><?php foreach ([30, 90, 365, 730, 3650, 0] as $days) : ?><option value="<?php echo 0 === $days ? 'indefinite' : esc_attr((string) $days); ?>" <?php selected((int) $settings['retention_days'], $days); ?>><?php echo esc_html(0 === $days ? __('Indefinite', 'mds-project-companion') : sprintf(_n('%d day', '%d days', $days, 'mds-project-companion'), $days)); ?></option><?php endforeach; ?></select></label>
                <p class="description"><?php esc_html_e('Choose whether Project Companion data is removed from Million Dollar Script → Settings → System → Delete Data On Uninstall.', 'mds-project-companion'); ?></p>
                <?php submit_button(__('Save diagnostics settings', 'mds-project-companion')); ?>
            </form>
        </section>
    <?php else : ?>
        <section class="mds3-card mds-project-companion__narrow"><h2><?php esc_html_e('Documentation', 'mds-project-companion'); ?></h2><p><?php esc_html_e('Project Companion includes local usage, privacy, security, connector setup, development, and troubleshooting guides.', 'mds-project-companion'); ?></p><ul><li><?php esc_html_e('Local recommendations work without a hosted account.', 'mds-project-companion'); ?></li><li><?php esc_html_e('Development mock clients are excluded from production packages.', 'mds-project-companion'); ?></li><li><?php esc_html_e('Hosted connector v1 uses explicit pairing, scoped credentials, exact previews, and administrator-triggered requests.', 'mds-project-companion'); ?></li></ul><?php if (class_exists('\MillionDollarScript\Extensions\Admin')) { echo wp_kses_post(\MillionDollarScript\Extensions\Admin::docs_button('mds-project-companion', __('Open Project Companion guides', 'mds-project-companion'))); } ?></section>
    <?php endif; ?>
</div>
