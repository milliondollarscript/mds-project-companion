# Using Project Companion

## Start locally

Open **Million Dollar Script > Project Companion**. The optional profile records a project name, summary, goals, focus, funding preference, external-service preference, and acceptable setup effort on the current WordPress site.

Plan, Create, Distribute, and Grow are disabled by default and can be enabled independently. Enabling a module does not connect an account, grant consent, or transfer information.

## Review recommendations

Recommendations are generated locally from the saved profile and current MDS capability snapshot. Filters include:

- Best fit
- Installed only
- Free only
- No external services

Every result identifies price status, service requirements, dependencies, alternatives, setup effort, and what remains possible without it. Paid status never improves ranking. Project Companion never buys, installs, activates, configures, or licenses another extension.

## Refresh capability discovery

Installed and active states come from the current WordPress runtime. Use **Diagnostics > Refresh public MDS discovery** to refresh available-extension and endpoint information. This explicit action calls public MDS REST routes inside WordPress and saves a timestamped cache.

Normal Project Companion page loads do not make hosted requests.

## Configure a hosted platform

A site operator can configure brand-neutral hosted details with the public filter:

```php
add_filter('million-dollar-script/project-companion/hosted-service-config', static function (array $config): array {
    $config['display_name'] = 'Project workspace';
    $config['api_origin'] = 'https://projects.example';
    $config['app_url'] = 'https://projects.example';
    $config['docs_url'] = 'https://projects.example/help';
    return $config;
});
```

`api_origin` is an origin only. Do not append `/api/v1`; Project Companion takes the API-major server path from connector contract `1.0`. Production origins require HTTPS. Explicit local development can use an HTTP loopback origin only when `WP_DEBUG` is enabled and `local_development` is set to `true`.

## Pair a project

In **Connection**:

1. Enable the modules needed for this installation.
2. Check contract compatibility.
3. Enter the hosted project UUID and a temporary authenticated platform token.
4. Begin pairing.
5. Open the hosted approval page and enter the displayed human code.
6. Return to WordPress and complete the approved pairing.

The temporary platform token is used for the pairing request only and is not saved. The short-lived pairing secret remains in an expiring WordPress transient. The returned connector credential is stored separately from ordinary extension settings in a non-autoloaded option.

If enabled modules later require scopes that were not granted during pairing, Project Companion shows a warning. Revoke and pair again to negotiate the updated least-privilege set.

## Preview and approve transfers

In **Shared Data**:

1. Enable only fields that may appear in previews.
2. Enable the source Plan or Create module.
3. Select fields and create an exact preview.
4. Review every value, purpose, source, privacy flag, and retention class.
5. Check the approval box only if this one transfer is acceptable.

Approval sends only the exact hash-bound preview. Plan transfers use the extension-owned generic project-context adapter. Create capability snapshots require all four contract fields so the hosted service receives a complete schema-valid snapshot.

Draft exports and aggregate metrics are not available in the administration UI until an owning extension exposes an approved public adapter or aggregate source contract.

## Fetch hosted views

Connected administrators can explicitly fetch:

- Plan summaries and hosted recommendations
- Create learning tracks, prompt packs, and milestones
- Distribute destination, promotion, and release summaries
- Grow experiments, findings, and next actions

Each button performs one read. Connector-safe results are cached locally for display and cleared when the connection is revoked.

Project Companion does not configure or run AI providers. Hosted BYOK settings and local-model execution remain in their owning platform or specialist extension and are not transferred through connector capability snapshots.

## Acknowledge actions

An administrator can mark a hosted action as seen, accepted for manual review, or dismissed. This only stores an acknowledgement in the hosted platform. It does not execute the action or mutate the WordPress site.

## Disconnect

Use **Revoke connection** to revoke the installation and remove the local connector credential and hosted-view cache. Credential rotation is also explicit. Deactivation does not make a remote request or revoke an installation.

## Troubleshooting

- If MDS is missing or not ready, Project Companion stays inactive and shows an administrator notice.
- If the MDS extension API major is not `1`, no business or network integration is initialized.
- If the hosted API origin is missing or fails safety checks, the disabled client keeps local features available.
- If compatibility fails, review the reported API major, contract, schema, scope, or minimum-client mismatch.
- If a preview is unavailable, verify that its module and selected sharing fields are enabled. Capability snapshots require all four Create fields.
- Pending previews expire after 15 minutes and fail when their canonical hash changes.
- A revoked installation clears the local connector credential and cached hosted views.
